<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryDemandService
{
    /** Project concrete SKU demand across shared compatible pools in event order. */
    public function project(?int $location = null): array
    {
        $products = Product::where('active', true)->where('is_event_usable', true)->get()->keyBy('id');
        $balances = DB::table('inventory_balances')->join('inventory_locations', 'inventory_locations.id', '=', 'location_id')
            ->where('inventory_locations.active', true)->where('inventory_locations.type', 'warehouse');
        if ($location) {
            $balances->where('location_id', $location);
        }
        $physical = $balances->selectRaw('product_id, SUM(quantity) as stock')->groupBy('product_id')->pluck('stock', 'product_id');
        $requirements = DB::table('event_requirements')->join('events', 'events.id', '=', 'event_id')->where('events.event_date', '>=', now())
            ->where('events.logistics_status', '!=', 'closed')->whereNotIn('events.status', ['closed', 'cancelled'])->whereNotIn('event_requirements.status', ['dispatched', 'closed'])
            ->orderBy('events.event_date')->orderBy('events.id')->orderBy('event_requirements.id')->select('event_requirements.*')->get();
        $events = DB::table('events')->whereIn('id', $requirements->pluck('event_id')->unique())->get()->keyBy('id');
        $constraints = app(PackageRequirementService::class)->constraintsFor('event_requirement', $requirements->pluck('id'));
        app(ProductMatcherService::class)->loadAttributes($products);
        $protectionRules = DB::table('inventory_protection_rules')->where('active', true)->orderByDesc('priority')->get();
        $allocationRules = DB::table('allocation_rules')->where('active', true)->orderByDesc('priority')->get();
        $allocationsQuery = DB::table('requirement_allocations')->whereIn('event_requirement_id', $requirements->pluck('id'))->whereIn('status', ['reserved', 'picked']);
        if ($location) {
            $allocationsQuery->where('warehouse_location_id', $location);
        }
        $allocationsByRequirement = $allocationsQuery->get()->groupBy('event_requirement_id');
        $used = [];
        $demand = [];
        foreach ($requirements as $requirement) {
            $event = $events[$requirement->event_id];
            $requirement->dashboard_constraints = $constraints[$requirement->id] ?? [];
            $allocated = 0;
            foreach ($allocationsByRequirement[$requirement->id] ?? [] as $allocation) {
                $quantity = (float) $allocation->quantity;
                $demand[$allocation->product_id] = ($demand[$allocation->product_id] ?? 0) + $quantity;
                $used[$allocation->product_id] = ($used[$allocation->product_id] ?? 0) + $quantity;
                $allocated += $quantity;
            }
            $remaining = max(0, (float) $requirement->quantity_required - $allocated);
            if ($remaining <= 0) {
                continue;
            }
            $candidates = [];
            foreach ($products as $product) {
                if (! app(ProductMatcherService::class)->compatible($product, $requirement)) {
                    continue;
                }
                $meta = InventoryData::json($requirement->metadata);
                if ($requirement->reference_value !== null && ($meta['value_is_hard_constraint'] ?? true)
                    && ($product->reference_value === null || abs((float) $product->reference_value - (float) $requirement->reference_value) > (float) ($requirement->value_tolerance ?? 0))) {
                    continue;
                }
                $stock = (float) ($physical[$product->id] ?? 0);
                $protection = app(InventoryProtectionService::class)->evaluate($product, $stock, $event, $protectionRules);
                if ($protection['excluded']) {
                    continue;
                }
                $available = max(0, $stock - $protection['protected_stock'] - ($used[$product->id] ?? 0));
                $rule = app(AllocationRuleService::class)->evaluate($product, $event, $requirement, ['available_stock' => $available], $allocationRules);
                if ($rule['excluded']) {
                    continue;
                }
                $candidates[] = ['id' => $product->id, 'available' => $available, 'score' => $available + $protection['score_delta'] + $rule['score_delta']];
            }
            usort($candidates, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']));
            foreach ($candidates as $candidate) {
                $quantity = min($remaining, $candidate['available']);
                $demand[$candidate['id']] = ($demand[$candidate['id']] ?? 0) + $quantity;
                $used[$candidate['id']] = ($used[$candidate['id']] ?? 0) + $quantity;
                $remaining -= $quantity;
            }
            // Assign the deficit to the highest-ranked compatible SKU as a purchase projection.
            // This is a suggestion only; it creates no allocation or physical reservation.
            if ($remaining > 0 && $candidates) {
                $id = $candidates[0]['id'];
                $demand[$id] = ($demand[$id] ?? 0) + $remaining;
                $used[$id] = ($used[$id] ?? 0) + $remaining;
            }
        }

        return $demand;
    }
}
