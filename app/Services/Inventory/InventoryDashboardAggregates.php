<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryDashboardAggregates
{
    public function summary(): array
    {
        $products = Product::where('active', true)->orderBy('id')->get();
        $attributes = DB::table('product_attribute_values')->join('attribute_definitions', 'attribute_definitions.id', '=', 'attribute_definition_id')->get()->groupBy('product_id');
        $effects = DB::table('product_effects')->get()->groupBy('product_id')->map(fn ($rows) => $rows->pluck('effect_id')->all())->all();
        $colors = DB::table('product_colors')->get()->groupBy('product_id')->map(fn ($rows) => $rows->pluck('color_id')->all());
        foreach ($products as $product) {
            $values = ['effects' => $effects[$product->id] ?? [], 'colors' => $colors[$product->id] ?? []];
            foreach ($attributes[$product->id] ?? [] as $row) {
                $values[$row->code] = match ($row->data_type) {
                    'number' => $row->value_number, 'boolean' => $row->value_boolean === null ? null : (bool) $row->value_boolean,
                    'json' => json_decode($row->value_json ?? 'null', true), default => $row->value_string,
                };
            }
            $product->setRelation('inventoryAttributeValues', $values);
        }
        $physical = DB::table('inventory_balances')->join('inventory_locations', 'inventory_locations.id', '=', 'location_id')->where('inventory_locations.type', 'warehouse')->where('inventory_locations.active', true)->selectRaw('product_id, SUM(quantity) as total')->groupBy('product_id')->pluck('total', 'product_id');
        $allocations = DB::table('requirement_allocations')->whereIn('status', ['reserved', 'picked'])->get();
        $reserved = $allocations->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
        $incoming = DB::table('operational_purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_id')->whereIn('purchases.status', ['ordered', 'partial'])->select('product_id', 'quantity', 'received_quantity', 'expected_date')->get()->groupBy('product_id');
        $protections = DB::table('inventory_protection_rules')->where('active', true)->orderByDesc('priority')->get();
        $rules = DB::table('allocation_rules')->where('active', true)->orderByDesc('priority')->get();
        $events = DB::table('events')->where('event_date', '>=', now(config('mobile.event_timezone'))->startOfDay()->toDateTimeString())->whereNotIn('status', ['closed', 'cancelled'])->where('logistics_status', '!=', 'closed')->orderBy('event_date')->orderBy('id')->get()->keyBy('id');
        $requirements = DB::table('event_requirements')->whereIn('event_id', $events->keys())->whereNotIn('status', ['dispatched', 'closed', 'cancelled'])->orderBy('id')->get()->groupBy('event_id');
        $constraints = DB::table('requirement_constraints')->where('owner_type', 'event_requirement')->get()->groupBy('owner_id')->map(fn ($rows) => $rows->map(fn ($r) => ['attribute_code' => $r->attribute_code, 'operator' => $r->operator, 'value' => json_decode($r->value, true), 'is_hard_constraint' => (bool) $r->is_hard_constraint, 'weight' => $r->weight])->all());
        $oldest = DB::table('inventory_movements')->whereNotNull('to_location_id')->selectRaw('product_id, MIN(occurred_at) as oldest')->groupBy('product_id')->pluck('oldest', 'product_id')->all();
        $matcher = app(ProductMatcherService::class);
        $protectionService = app(InventoryProtectionService::class);
        $low = 0;
        $value = 0;
        foreach ($products as $product) {
            $stock = (float) ($physical[$product->id] ?? 0);
            $protection = $protectionService->evaluate($product, $stock, null, $protections);
            $low += max(0, $stock - ($reserved[$product->id] ?? 0) - $protection['protected_stock']) <= $product->minimum_stock ? 1 : 0;
            $value += $stock * (float) $product->purchase_cost;
        }
        $used = [];
        $deficits = [];
        foreach ($events as $event) {
            foreach ($requirements[$event->id] ?? [] as $requirement) {
                $requirement->dashboard_constraints = $constraints[$requirement->id] ?? [];
                $candidates = [];
                foreach ($products as $product) {
                    if (! $matcher->compatible($product, $requirement)) {
                        continue;
                    }
                    $fits = $requirement->reference_value === null || ($product->reference_value !== null && abs((float) $product->reference_value - (float) $requirement->reference_value) <= (float) $requirement->value_tolerance);
                    if (! $fits && (InventoryData::json($requirement->metadata)['value_is_hard_constraint'] ?? true)) {
                        continue;
                    }
                    $stock = (float) ($physical[$product->id] ?? 0);
                    $protection = $protectionService->evaluate($product, $stock, $event, $protections);
                    $available = ['available_stock' => max(0, $stock - ($reserved[$product->id] ?? 0) - $protection['protected_stock']), 'protected_stock' => $protection['protected_stock'], 'protection' => $protection];
                    $rule = app(AllocationRuleService::class)->evaluate($product, $event, $requirement, $available, $rules);
                    if ($protection['excluded'] || $rule['excluded']) {
                        continue;
                    }
                    $score = app(ProductAllocationScoringService::class)->score($product, $requirement, $available, $requirement->dashboard_constraints, $fits, ['effects' => $effects, 'oldest' => $oldest, 'allocated_products' => $allocations->where('event_requirement_id', $requirement->id)->pluck('product_id')]);
                    $arriving = collect($incoming[$product->id] ?? [])->filter(fn ($i) => $i->expected_date && substr($i->expected_date, 0, 10) <= substr($event->event_date, 0, 10))->sum(fn ($i) => (float) $i->quantity - (float) $i->received_quantity);
                    $candidates[] = ['id' => $product->id, 'score' => $score['score'] + $rule['score_delta'], 'capacity' => max(0, $stock - $protection['protected_stock'] + $arriving)];
                }
                usort($candidates, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']));
                $remaining = (float) $requirement->quantity_required;
                foreach ($candidates as $candidate) {
                    $take = min($remaining, max(0, $candidate['capacity'] - ($used[$candidate['id']] ?? 0)));
                    $used[$candidate['id']] = ($used[$candidate['id']] ?? 0) + $take;
                    $remaining -= $take;
                }
                if ($remaining > 0.0001) {
                    $deficits[$event->id] = true;
                }
            }
        }
        $adminIds = DB::table('roles')->whereIn('name', ['root', 'admin'])->pluck('id');
        $admins = DB::table('users')->where(fn ($q) => $q->whereIn('role_id', $adminIds)->orWhere('status', '!=', 'active'))->pluck('id')->merge(DB::table('user_role')->whereIn('role_id', $adminIds)->pluck('user_id'));

        return ['upcoming_events' => $events->count(), 'active_employees' => DB::table('employees')->where('status', 'active')->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $admins))->count(),
            'low_stock_products' => $low, 'deficit_events' => count($deficits), 'pending_purchases' => DB::table('purchases')->whereIn('status', ['ordered', 'partial'])->count(), 'inventory_value' => round($value, 2)];
    }
}
