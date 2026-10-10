<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchases_counts_sales_and_filters_keep_balances_separate_by_area(): void
    {
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->actingAs($admin, 'api');
        $product = app(ProductCatalogService::class)->save(['name' => 'Artículo', 'product_role_id' => 1, 'unit' => 'pz'], $admin->id)['id'];
        $locations = [];
        foreach (['polvorin', 'stand', 'raw_material'] as $i => $area) {
            $locations[$area] = InventoryData::insert('inventory_locations', ['name' => $area, 'code' => $area, 'type' => $area === 'stand' ? 'stand' : 'warehouse', 'inventory_area' => $area]);
            $purchase = $this->postJson('/api/purchases', ['purchase_date' => '2026-10-10', 'status' => 'received', 'warehouse_location_id' => $locations[$area], 'items' => [['product_id' => $product, 'quantity' => ($i + 1) * 10, 'unit_cost' => 5]]])->assertCreated()->assertJsonPath('purchase.destination_location_id', $locations[$area]);
            $this->getJson('/api/inventory?inventory_area='.$area)->assertOk()->assertJsonPath('data.0.physical_stock', ($i + 1) * 10);
        }
        $this->postJson('/api/inventory/counts', ['product_id' => $product, 'location_id' => $locations['raw_material'], 'quantity' => 35, 'expected_quantity' => 30, 'purpose' => 'correction', 'notes' => 'Conteo de materia prima'])->assertOk();
        $this->postJson('/api/sales', ['date' => '2026-10-10', 'client_name' => 'Mostrador', 'status' => 'completed', 'warehouse_location_id' => $locations['stand'], 'items' => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 10]]])->assertCreated();
        foreach (['polvorin' => 10, 'stand' => 18, 'raw_material' => 35] as $area => $stock) {
            $this->getJson('/api/inventory?inventory_area='.$area)->assertOk()->assertJsonPath('data.0.physical_stock', $stock);
        }
        $this->getJson('/api/inventory?inventory_area=stand&location_id='.$locations['polvorin'])->assertUnprocessable();
        $this->putJson('/api/inventory/catalogs/locations/'.$locations['polvorin'], ['inventory_area' => 'raw_material'])->assertUnprocessable();
    }

    public function test_catalog_storage_area_validation_and_pending_purchase_projection(): void
    {
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->actingAs($admin, 'api');
        $product = app(ProductCatalogService::class)->save(['name' => 'Artículo', 'product_role_id' => 1, 'unit' => 'pz'], $admin->id)['id'];
        $raw = $this->postJson('/api/inventory/catalogs/locations', ['name' => 'Materia prima', 'code' => 'RAW', 'type' => 'warehouse', 'inventory_area' => 'raw_material'])->assertOk()->json('id');
        $this->postJson('/api/inventory/catalogs/locations', ['name' => 'Inválido', 'code' => 'BAD', 'type' => 'stand', 'inventory_area' => 'raw_material'])->assertUnprocessable();
        $this->postJson('/api/purchases', ['purchase_date' => '2026-10-10', 'status' => 'ordered', 'warehouse_location_id' => $raw, 'items' => [['product_id' => $product, 'quantity' => 10, 'unit_cost' => 5]]])->assertCreated();
        $this->getJson('/api/inventory?inventory_area=raw_material')->assertOk()->assertJsonPath('data.0.incoming_stock', 10);
        $this->getJson('/api/inventory?inventory_area=polvorin')->assertOk()->assertJsonPath('data.0.incoming_stock', 0);
    }
}
