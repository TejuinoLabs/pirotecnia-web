<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductMatcherService
{
    public function attribute(Product $product, string $code): mixed
    {
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
        if (in_array($code, ['shots', 'caliber', 'duration', 'legacy_equipment_id'], true)) {
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
        foreach (app(PackageRequirementService::class)->constraints('event_requirement', $requirement->id) as $constraint) {
            if ($constraint['is_hard_constraint'] && ! $this->constraintMatch($product, $constraint)) {
                return false;
            }
        }

        return true;
    }

    public function candidates(object $event, object $requirement, ?int $location = null, bool $includeProtected = true, ?int $ignoreAllocation = null): array
    {
        $constraints = app(PackageRequirementService::class)->constraints('event_requirement', $requirement->id);
        $results = [];
        $demand = app(InventoryDemandService::class)->project($location);
        foreach (Product::where('active', true)->where('is_event_usable', true)->get() as $product) {
            $stock = app(InventoryAvailabilityService::class)->stock($product, $location, $event, $ignoreAllocation, $demand);
            $compatible = $this->compatible($product, $requirement);
            $valueCompatible = $requirement->reference_value === null || ($product->reference_value !== null && abs((float) $product->reference_value - (float) $requirement->reference_value) <= (float) ($requirement->value_tolerance ?? 0));
            $strictValue = InventoryData::json($requirement->metadata)['value_is_hard_constraint'] ?? true;
            if (! $valueCompatible && $strictValue) {
                $compatible = false;
            }
            $scoring = app(ProductAllocationScoringService::class)->score($product, $requirement, $stock, $constraints, $valueCompatible);
            $ruleResult = app(AllocationRuleService::class)->evaluate($product, $event, $requirement, $stock);
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
