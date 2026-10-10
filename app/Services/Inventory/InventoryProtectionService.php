<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryProtectionService
{
    public function evaluate(Product $product, float $physical, ?object $event = null): array
    {
        $protected = 0;
        $score = 0;
        $excluded = false;
        $rules = [];
        $reasons = [];
        foreach (DB::table('inventory_protection_rules')->where('active', true)->orderByDesc('priority')->get() as $rule) {
            if ($rule->product_id && $rule->product_id !== $product->id) {
                continue;
            }
            if ($rule->category_id && $rule->category_id !== $product->category_id) {
                continue;
            }
            if (! app(ProductMatcherService::class)->filtersMatch($product, InventoryData::json($rule->attribute_filters))) {
                continue;
            }
            $type = $event?->event_type_id;
            $applicable = InventoryData::json($rule->applicable_event_types);
            $exceptions = InventoryData::json($rule->excluded_event_types);
            if ($type && ($applicable && ! in_array($type, $applicable, true) || in_array($type, $exceptions, true))) {
                continue;
            }
            $rules[] = $rule->id;
            $reasons[] = $rule->message ?: $rule->name;
            if ($rule->action === 'protect_stock') {
                $protected = max($protected, (float) ($rule->protected_quantity ?? 0), $physical * (float) ($rule->protected_percentage ?? 0) / 100);
            }
            if ($rule->action === 'exclude') {
                $excluded = true;
            }
            if ($rule->action === 'penalize') {
                $score -= 30;
            }
            if ($rule->action === 'prefer') {
                $score += 30;
            }
        }

        return ['protected_stock' => min($physical, round($protected, 3)), 'score_delta' => $score, 'excluded' => $excluded, 'rules' => $rules, 'reasons' => $reasons];
    }
}
