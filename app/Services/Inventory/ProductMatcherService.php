<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductMatcherService
{
    /** Load catalog attributes once per calculation, never cache stock across requests. */
    public function loadAttributes(\Illuminate\Support\Collection $products): void
    {
        $ids = $products->pluck('id');
        $attributes = DB::table('product_attribute_values')->join('attribute_definitions', 'attribute_definitions.id', '=', 'attribute_definition_id')
            ->whereIn('product_id', $ids)->get()->groupBy('product_id');
        $effects = DB::table('product_effects')->whereIn('product_id', $ids)->get()->groupBy('product_id');
        $colors = DB::table('product_colors')->whereIn('product_id', $ids)->get()->groupBy('product_id');
        foreach ($products as $product) {
            $values = ['effects' => collect($effects[$product->id] ?? [])->pluck('effect_id')->all(),
                'colors' => collect($colors[$product->id] ?? [])->pluck('color_id')->all()];
            foreach ($attributes[$product->id] ?? [] as $row) {
                $values[$row->code] = match ($row->data_type) {
                    'number' => $row->value_number === null ? null : (float) $row->value_number,
                    'boolean' => $row->value_boolean === null ? null : (bool) $row->value_boolean,
                    'json' => json_decode($row->value_json ?? 'null', true), default => $row->value_string,
                };
            }
            $product->setRelation('inventoryAttributeValues', $values);
        }
    }

    public function attribute(Product $product, string $code): mixed
    {
        if ($product->relationLoaded('inventoryAttributeValues')) {
            $values = $product->getRelation('inventoryAttributeValues');

            return array_key_exists($code, $values) ? $values[$code] : $product->getAttribute($code);
        }
        if (in_array($code, ['effects', 'colors'], true)) {
            return DB::table('product_'.$code)->where('product_id', $product->id)->pluck($code === 'effects' ? 'effect_id' : 'color_id')->all();
        }
        $row = DB::table('product_attribute_values')->join('attribute_definitions', 'attribute_definitions.id', '=', 'attribute_definition_id')->where('product_id', $product->id)->where('code', $code)->first();
        if ($row) {
            return match ($row->data_type) {
                'number' => $row->value_number === null ? null : (float) $row->value_number,
                'boolean' => $row->value_boolean === null ? null : (bool) $row->value_boolean,
                'json' => json_decode($row->value_json ?? 'null', true),
                default => $row->value_string,
            };
        }
        // Adapter for existing numeric product fields, never infer values from names.
        if (in_array($code, ['shape', 'shots', 'caliber', 'duration', 'legacy_equipment_id'], true)) {
            return $product->getAttribute($code);
        }

        return null;
    }

    public function constraintMatch(Product $product, array $constraint): bool
    {
        $actual = $this->attribute($product, $constraint['attribute_code']);
        $value = $constraint['value'];
        if ($actual === null) {
            return false;
        }
        $equal = fn ($a, $b) => is_numeric($a) && is_numeric($b) ? (float) $a === (float) $b : $a === $b;
        $numeric = is_numeric($actual) && is_numeric($value);

        return match ($constraint['operator']) {
            '=' => $equal($actual, $value), '!=' => ! $equal($actual, $value),
            '>' => $numeric && $actual > $value, '>=' => $numeric && $actual >= $value,
            '<' => $numeric && $actual < $value, '<=' => $numeric && $actual <= $value,
            'between' => is_numeric($actual) && is_array($value) && count($value) === 2 && $actual >= $value[0] && $actual <= $value[1],
            'in' => is_array($value) && collect($value)->contains(fn ($v) => $equal($actual, $v)),
            'not_in' => is_array($value) && ! collect($value)->contains(fn ($v) => $equal($actual, $v)),
            'contains' => is_array($actual) ? collect($actual)->contains(fn ($v) => $equal($v, $value)) : is_string($actual) && is_string($value) && str_contains($actual, $value),
            default => false,
        };
    }

    public function filtersMatch(Product $product, array $filters): bool
    {
        foreach ($filters as $code => $value) {
            $constraint = is_array($value) && isset($value['operator']) ? $value + ['attribute_code' => $code] : ['attribute_code' => $code, 'operator' => '=', 'value' => $value];
            if (! $this->constraintMatch($product, $constraint)) {
                return false;
            }
        }

        return true;
    }

    public function compatible(Product $product, object $requirement): bool
    {
        if (! $product->active || ! $product->is_event_usable || ! $product->tracks_inventory) {
            return false;
        }
        if ($requirement->requirement_type === 'equipment' && ! $product->is_returnable) {
            return false;
        }
        if ($requirement->product_id && $requirement->product_id !== $product->id && ! $requirement->allow_substitution) {
            return false;
        }
        if ($requirement->category_id && $requirement->category_id !== $product->category_id) {
            return false;
        }
        if ($requirement->product_id && ! $requirement->category_id && $requirement->product_id !== $product->id) {
            return false;
        }
        foreach ($requirement->dashboard_constraints ?? app(PackageRequirementService::class)->constraints('event_requirement', $requirement->id) as $constraint) {
            if ($constraint['is_hard_constraint'] && ! $this->constraintMatch($product, $constraint)) {
                return false;
            }
        }

        return true;
    }

    public function candidateContext(?int $location = null, ?int $ignoreAllocation = null): array
    {
        $products = Product::where('active', true)->where('is_event_usable', true)->get();
        $this->loadAttributes($products);

        return [
            'products' => $products,
            'stock' => app(InventoryAvailabilityService::class)->context($location, $ignoreAllocation, null, $products->pluck('id')),
            'rules' => DB::table('allocation_rules')->where('active', true)->orderByDesc('priority')->get(),
            'effects' => $products->mapWithKeys(fn ($p) => [$p->id => $this->attribute($p, 'effects')])->all(),
            'oldest' => DB::table('inventory_movements')->whereNotNull('to_location_id')->selectRaw('product_id, MIN(occurred_at) as oldest')->groupBy('product_id')->pluck('oldest', 'product_id')->all(),
            'allocations' => DB::table('requirement_allocations')->whereIn('status', ['reserved', 'picked'])->get(['event_requirement_id', 'product_id'])->groupBy('event_requirement_id'),
        ];
    }

    public function candidates(object $event, object $requirement, ?int $location = null, bool $includeProtected = true, ?int $ignoreAllocation = null, ?array $demandProjection = null, ?array $context = null): array
    {
        $constraints = $requirement->dashboard_constraints ?? app(PackageRequirementService::class)->constraints('event_requirement', $requirement->id);
        $requirement = clone $requirement;
        $requirement->dashboard_constraints = $constraints;
        $results = [];
        $demand = $demandProjection ?? app(InventoryDemandService::class)->project($location);
        $context ??= $this->candidateContext($location, $ignoreAllocation);
        foreach ($context['products'] as $product) {
            $stock = app(InventoryAvailabilityService::class)->stock($product, $location, $event, $ignoreAllocation, $demand, null, $context['stock']);
            $compatible = $this->compatible($product, $requirement);
            $valueCompatible = $requirement->reference_value === null || ($product->reference_value !== null && abs((float) $product->reference_value - (float) $requirement->reference_value) <= (float) ($requirement->value_tolerance ?? 0));
            $strictValue = InventoryData::json($requirement->metadata)['value_is_hard_constraint'] ?? true;
            if (! $valueCompatible && $strictValue) {
                $compatible = false;
            }
            $scoring = app(ProductAllocationScoringService::class)->score($product, $requirement, $stock, $constraints, $valueCompatible, ['effects' => $context['effects'], 'oldest' => $context['oldest'], 'allocated_products' => collect($context['allocations'][$requirement->id] ?? [])->pluck('product_id')]);
            $ruleResult = app(AllocationRuleService::class)->evaluate($product, $event, $requirement, $stock, $context['rules']);
            $scoring['score'] += $ruleResult['score_delta'];
            $scoring['reasons'] = array_merge($scoring['reasons'], $ruleResult['reasons']);
            if ($ruleResult['excluded'] || $stock['protection']['excluded']) {
                $compatible = false;
            }
            $status = ! $compatible ? 'incompatible' : ($stock['physical_stock'] <= 0 ? 'out_of_stock' : ($stock['protected_stock'] > 0 ? 'protected' : (! $valueCompatible ? 'exception' : ($stock['available_stock'] >= $requirement->quantity_required ? 'recommended' : 'compatible'))));
            if (! $compatible) {
                $scoring['reasons'] = ['No cumple categoría, producto, restricciones o reglas obligatorias'];
            }
            if (! $includeProtected && $status === 'protected') {
                continue;
            }
            $results[] = ['product_id' => $product->id, 'name' => $product->display_name, 'sku' => $product->sku,
                'reference_value' => $product->reference_value, 'effects' => $this->attribute($product, 'effects'), 'colors' => $this->attribute($product, 'colors'),
                'status' => $status, 'compatible' => $compatible, ...$stock, ...$scoring];
        }

        usort($results, fn ($a, $b) => ($b['compatible'] <=> $a['compatible']) ?: ($b['score'] <=> $a['score']) ?: ($a['product_id'] <=> $b['product_id']));

        return $results;
    }
}
