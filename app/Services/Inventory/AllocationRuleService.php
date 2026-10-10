<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class AllocationRuleService
{
    public function evaluate(Product $product, object $event, object $requirement, array $stock): array
    {
        $score = 0;
        $excluded = false;
        $reasons = [];
        foreach (DB::table('allocation_rules')->where('active', true)->orderByDesc('priority')->get() as $rule) {
            $condition = InventoryData::json($rule->condition_json);
            $action = InventoryData::json($rule->action_json);
            if (isset($condition['category_id']) && $condition['category_id'] !== $product->category_id) {
                continue;
            }
            if (isset($condition['event_type_id']) && $condition['event_type_id'] !== $event->event_type_id) {
                continue;
            }
            if (isset($condition['product_id']) && $condition['product_id'] !== $product->id) {
                continue;
            }
            if (isset($condition['profile_id']) && $condition['profile_id'] !== $requirement->profile_id) {
                continue;
            }
            if (isset($condition['package_id']) && (InventoryData::json($requirement->snapshot)['package_id'] ?? null) !== $condition['package_id']) {
                continue;
            }
            if (! app(ProductMatcherService::class)->filtersMatch($product, $condition['attributes'] ?? [])) {
                continue;
            }
            if (isset($condition['min_available']) && $stock['available_stock'] < $condition['min_available']) {
                continue;
            }
            $score += (float) ($action['score_delta'] ?? 0);
            $excluded = $excluded || ($action['exclude'] ?? false);
            $reasons[] = $rule->name;
        }

        return ['score_delta' => $score, 'excluded' => $excluded, 'reasons' => $reasons];
    }
}
