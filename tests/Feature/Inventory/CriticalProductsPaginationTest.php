<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory;
use App\Models\User;
use App\Services\Inventory\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CriticalProductsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_critical_products_can_be_reached_and_dashboard_totals_do_not_truncate(): void
    {
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $inventory = new Inventory;
        $inventory->forceFill(['name' => 'Polvorín', 'location' => 'Principal'])->save();
        $ids = [];
        for ($i = 1; $i <= 16; $i++) {
            $product = app(ProductCatalogService::class)->save(['name' => sprintf('Producto %02d', $i), 'product_role_id' => 1, 'unit' => 'pz', 'minimum_stock' => 1], $admin->id);
            $ids[] = $product['id'];
            $inventory->products()->attach($product['id'], ['quantity' => 0, 'price' => 10]);
        }
        $this->actingAs($admin)->withoutVite();
        $this->get('/panel/inventories')->assertOk()->assertViewHas('criticalProducts', fn ($page) => $page->total() === 16 && $page->count() === 15 && $page->hasMorePages())->assertSee('critical_page=2', false);
        $this->get('/panel/inventories?critical_page=2')->assertOk()->assertViewHas('criticalProducts', fn ($page) => $page->currentPage() === 2 && $page->count() === 1 && $page->first()['id'] === $ids[15])->assertSee('Producto 16');
        $this->get('/home?low_stock_page=2')->assertOk()->assertViewHas('lowStockCount', '16')->assertViewHas('criticalLowStockCount', 16)->assertViewHas('lowStockItems', fn ($page) => $page->total() === 16 && $page->count() === 1 && $page->currentPage() === 2);
    }
}
