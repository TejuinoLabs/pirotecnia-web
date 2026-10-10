<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductAllocationScoringService
{
    public function score(Product $product, object $requirement, array $stock, array $constraints, bool $valueCompatible): array
    {
        $weights = config('inventory.scoring');
        $score = $weights['base'];
        $reasons = ['Cumple restricciones obligatorias'];
        if ($stock['available_stock'] >= $requirement->quantity_required * 2) {
            $score += $weights['high_stock'];
            $reasons[] = 'Stock alto';
        }
        if ($stock['available_stock'] < $requirement->quantity_required) {
            $score += $weights['low_stock'];
            $reasons[] = 'Stock bajo';
        }
        if ($stock['protected_stock'] > 0) {
            $score += $weights['protected'];
        }
        if ($requirement->reference_value !== null) {
            $score += $valueCompatible ? $weights['near_value'] : $weights['outside_value'];
            $reasons[] = $valueCompatible ? 'Valor dentro de tolerancia' : 'Valor fuera de tolerancia';
        }
        foreach ($constraints as $constraint) {
            if (! $constraint['is_hard_constraint'] && app(ProductMatcherService::class)->constraintMatch($product, $constraint)) {
                $score += (float) ($constraint['weight'] ?? 5);
            }
        }
        $meta = InventoryData::json($requirement->metadata);
        if ($meta['prefer_effect_variety'] ?? false) {
            $used = DB::table('requirement_allocations')->where('event_requirement_id', $requirement->id)->whereIn('status', ['reserved', 'picked'])->pluck('product_id');
            $effects = DB::table('product_effects')->where('product_id', $product->id)->pluck('effect_id');
            if ($effects->isNotEmpty() && ! DB::table('product_effects')->whereIn('product_id', $used)->whereIn('effect_id', $effects)->exists()) {
                $score += $weights['variety'];
                $reasons[] = 'Aporta variedad de efectos';
            }
        }
        if ($meta['prefer_old_stock'] ?? false) {
            $oldest = DB::table('inventory_movements')->where('product_id', $product->id)->whereNotNull('to_location_id')->min('occurred_at');
            if ($oldest && strtotime($oldest) < strtotime('-30 days')) {
                $score += $weights['rotation'];
                $reasons[] = 'Prioridad de rotación';
            }
        }
        $score += $stock['protection']['score_delta'];

        return ['score' => $score, 'reasons' => array_merge($reasons, $stock['protection']['reasons'])];
    }
}
