<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventCoverageService
{
    public function coverage(int $eventId, ?int $location = null, bool $saveAlerts = true): array
    {
        $target = InventoryData::get('events', $eventId);
        $matcher = app(ProductMatcherService::class);
        // Simulate demand chronologically across shared compatible pools, so two
        // requirements cannot both count the same physical units as coverage.
        $rows = DB::table('event_requirements')->join('events', 'events.id', '=', 'event_requirements.event_id')
            ->where('events.event_date', '<=', $target->event_date)
            ->where(function ($query) use ($eventId) {
                $query->where('events.event_date', '>=', now())->orWhere('events.id', $eventId);
            })
            ->where('events.logistics_status', '!=', 'closed')->whereNotIn('event_requirements.status', ['dispatched', 'closed'])
            ->orderBy('events.event_date')->orderBy('events.id')->orderBy('event_requirements.id')
            ->select('event_requirements.*')->get();
        $used = [];
        $results = [];
        $overall = 'covered';
        foreach ($rows as $requirement) {
            $event = InventoryData::get('events', $requirement->event_id);
            $candidates = array_filter($matcher->candidates($event, $requirement, $location), fn ($c) => $c['compatible']);
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
                $arriving = (float) DB::table('operational_purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_id')
                    ->where('product_id', $productId)->whereIn('purchases.status', ['ordered', 'partial'])
                    ->whereNotNull('purchases.expected_date')->whereDate('purchases.expected_date', '<=', $event->event_date)
                    ->selectRaw('COALESCE(SUM(quantity - received_quantity), 0) as incoming')->value('incoming');
                $incoming += $arriving;
                $capacity = max(0, $candidate['physical_stock'] - $candidate['protected_stock'] + $arriving);
                $prior += $used[$productId] ?? 0;
                $available = max(0, $capacity - ($used[$productId] ?? 0));
                $usable += $available;
                $take = min($available, $remaining);
                $used[$productId] = ($used[$productId] ?? 0) + $take;
                $remaining -= $take;
            }
            if ($requirement->event_id !== $eventId) {
                continue;
            }
            $status = $remaining > 0 ? 'critical' : ($usable - (float) $requirement->quantity_required < (float) $requirement->quantity_required ? 'warning' : 'covered');
            if ($status === 'covered' && $physical - $protected - $prior < (float) $requirement->quantity_required) {
                $status = 'warning';
            }
            if ($status === 'critical' || ($status === 'warning' && $overall !== 'critical')) {
                $overall = $status;
            }
            $result = ['event_requirement_id' => $requirement->id, 'description' => $requirement->requirement_type.' #'.$requirement->id,
                'required' => (float) $requirement->quantity_required, 'compatible_stock' => $physical, 'protected_stock' => $protected,
                'usable_stock' => $usable, 'incoming_stock' => $incoming, 'future_demand' => $prior, 'projected_available' => $usable - (float) $requirement->quantity_required,
                'deficit' => $remaining, 'status' => $status];
            $results[] = $result;
            if ($saveAlerts) {
                if ($status === 'covered') {
                    DB::table('inventory_alerts')->where('event_requirement_id', $requirement->id)->delete();
                } else {
                    DB::table('inventory_alerts')->updateOrInsert(['event_requirement_id' => $requirement->id], InventoryData::encode(['event_id' => $eventId, 'severity' => $status, 'deficit' => $remaining, 'details' => $result, 'created_at' => now(), 'updated_at' => now()]));
                }
            }
        }

        return ['event_id' => $eventId, 'status' => $overall, 'requirements' => $results];
    }
}
