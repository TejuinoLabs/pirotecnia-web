<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventCoverageService
{
    public function coverage(int $eventId, ?int $location = null, bool $saveAlerts = true): array
    {
        return $this->coverageMany([$eventId], $location, $saveAlerts)[$eventId];
    }

    /** Simulate shared future stock once for a dashboard's event set. */
    public function coverageMany(array $eventIds, ?int $location = null, bool $saveAlerts = false): array
    {
        if (! $eventIds) {
            return [];
        }
        $targets = DB::table('events')->whereIn('id', $eventIds)->get()->keyBy('id');
        abort_unless($targets->count() === count(array_unique($eventIds)), 404);
        $lastDate = $targets->max('event_date');
        $matcher = app(ProductMatcherService::class);
        // Simulate demand chronologically across shared compatible pools, so two
        // requirements cannot both count the same physical units as coverage.
        $rows = DB::table('event_requirements')->join('events', 'events.id', '=', 'event_requirements.event_id')
            ->where('events.event_date', '<=', $lastDate)
            ->where(function ($query) use ($eventIds) {
                $query->where('events.event_date', '>=', now())->orWhereIn('events.id', $eventIds);
            })
            ->where('events.logistics_status', '!=', 'closed')->whereNotIn('events.status', ['closed', 'cancelled'])->whereNotIn('event_requirements.status', ['dispatched', 'closed'])
            ->orderBy('events.event_date')->orderBy('events.id')->orderBy('event_requirements.id')
            ->select('event_requirements.*')->get();
        $events = DB::table('events')->whereIn('id', $rows->pluck('event_id')->unique())->get()->keyBy('id');
        $constraints = app(PackageRequirementService::class)->constraintsFor('event_requirement', $rows->pluck('id'));
        $context = $rows->isEmpty() ? null : $matcher->candidateContext($location);
        $demand = $rows->isEmpty() ? [] : app(InventoryDemandService::class)->project($location);
        $incomingByProduct = DB::table('operational_purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_id')
            ->whereIn('purchases.status', ['ordered', 'partial'])->whereNotNull('purchases.expected_date')
            ->get(['product_id', 'quantity', 'received_quantity', 'expected_date'])->groupBy('product_id');
        $used = [];
        $coverage = $targets->map(fn ($event) => ['event_id' => $event->id, 'status' => 'covered', 'requirements' => []])->all();
        foreach ($rows as $requirement) {
            $event = $events[$requirement->event_id];
            $requirement->dashboard_constraints = $constraints[$requirement->id] ?? [];
            $candidates = array_filter($matcher->candidates($event, $requirement, $location, true, null, $demand, $context), fn ($c) => $c['compatible']);
            $physical = 0;
            $protected = 0;
            $usable = 0;
            $prior = 0;
            $incoming = 0;
            $remaining = (float) $requirement->quantity_required;
            foreach ($candidates as $candidate) {
                $productId = $candidate['product_id'];
                $physical += $candidate['physical_stock'];
                $protected += $candidate['protected_stock'];
                $arriving = collect($incomingByProduct[$productId] ?? [])
                    ->filter(fn ($item) => substr($item->expected_date, 0, 10) <= substr($event->event_date, 0, 10))
                    ->sum(fn ($item) => (float) $item->quantity - (float) $item->received_quantity);
                $incoming += $arriving;
                $capacity = max(0, $candidate['physical_stock'] - $candidate['protected_stock'] + $arriving);
                $prior += $used[$productId] ?? 0;
                $available = max(0, $capacity - ($used[$productId] ?? 0));
                $usable += $available;
                $take = min($available, $remaining);
                $used[$productId] = ($used[$productId] ?? 0) + $take;
                $remaining -= $take;
            }
            if (! isset($coverage[$requirement->event_id])) {
                continue;
            }
            $status = $remaining > 0 ? 'critical' : ($usable - (float) $requirement->quantity_required < (float) $requirement->quantity_required ? 'warning' : 'covered');
            if ($status === 'covered' && $physical - $protected - $prior < (float) $requirement->quantity_required) {
                $status = 'warning';
            }
            if ($status === 'critical' || ($status === 'warning' && $coverage[$requirement->event_id]['status'] !== 'critical')) {
                $coverage[$requirement->event_id]['status'] = $status;
            }
            $result = ['event_requirement_id' => $requirement->id, 'description' => $requirement->requirement_type.' #'.$requirement->id,
                'required' => (float) $requirement->quantity_required, 'compatible_stock' => $physical, 'protected_stock' => $protected,
                'usable_stock' => $usable, 'incoming_stock' => $incoming, 'future_demand' => $prior, 'projected_available' => $usable - (float) $requirement->quantity_required,
                'deficit' => $remaining, 'status' => $status];
            $coverage[$requirement->event_id]['requirements'][] = $result;
            if ($saveAlerts) {
                if ($status === 'covered') {
                    DB::table('inventory_alerts')->where('event_requirement_id', $requirement->id)->delete();
                } else {
                    DB::table('inventory_alerts')->updateOrInsert(['event_requirement_id' => $requirement->id], InventoryData::encode(['event_id' => $requirement->event_id, 'severity' => $status, 'deficit' => $remaining, 'details' => $result, 'created_at' => now(), 'updated_at' => now()]));
                }
            }
        }

        return $coverage;
    }
}
