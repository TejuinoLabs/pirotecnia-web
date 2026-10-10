<?php

namespace Tests\Unit\Inventory;

use App\Models\Product;
use App\Services\Inventory\ProductAllocationScoringService;
use Tests\TestCase;

class ProductAllocationScoringServiceTest extends TestCase
{
    public function test_high_stock_and_reference_value_rank_above_low_protected_stock(): void
    {
        $requirement = (object) ['quantity_required' => 3, 'reference_value' => 2500, 'metadata' => '{}'];
        $base = ['protected_stock' => 0, 'available_stock' => 10, 'protection' => ['score_delta' => 0, 'reasons' => []]];
        $scoring = app(ProductAllocationScoringService::class);
        $recommended = $scoring->score(new Product, $requirement, $base, [], true);
        $protected = $scoring->score(new Product, $requirement, array_replace($base, ['protected_stock' => 6, 'available_stock' => 2]), [], true);
        $this->assertGreaterThan($protected['score'], $recommended['score']);
        $this->assertContains('Stock alto', $recommended['reasons']);
        config(['inventory.scoring.high_stock' => 40]);
        $this->assertEquals($recommended['score'] + 20, $scoring->score(new Product, $requirement, $base, [], true)['score']);
    }
}
