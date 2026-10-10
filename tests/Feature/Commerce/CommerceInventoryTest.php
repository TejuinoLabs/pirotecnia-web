<?php

namespace Tests\Feature\Commerce;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommerceInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private Product $material;

    private int $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert([['id' => 1, 'name' => 'Producto'], ['id' => 2, 'name' => 'Material']]);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->warehouse = InventoryData::insert('inventory_locations', ['name' => 'Principal', 'code' => 'MAIN', 'type' => 'warehouse']);
        $this->product = Product::findOrFail(app(ProductCatalogService::class)->save(['name' => 'Bomba', 'caliber' => '3', 'product_role_id' => 1, 'unit' => 'pz', 'sale_price' => 100], $this->admin->id)['id']);
        $this->material = Product::findOrFail(app(ProductCatalogService::class)->save(['name' => 'Mecha', 'product_role_id' => 2, 'unit' => 'm', 'sale_price' => 20], $this->admin->id)['id']);
        app(InventoryMovementService::class)->record($this->product->id, 10, null, $this->warehouse, 'adjustment_in', $this->admin->id, 'Apertura');
        app(InventoryMovementService::class)->record($this->material->id, 20, null, $this->warehouse, 'adjustment_in', $this->admin->id, 'Apertura');
        $this->actingAs($this->admin, 'api');
    }

    private function sale(array $changes = []): array
    {
        return array_replace(['date' => '2026-10-10', 'client_name' => 'Cliente de mostrador', 'warehouse_location_id' => $this->warehouse, 'status' => 'completed', 'discount' => 10, 'tax' => 32, 'items' => [['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100], ['product_id' => $this->material->id, 'quantity' => 1.5, 'unit_price' => 20]]], $changes);
    }

    private function purchase(array $changes = []): array
    {
        return array_replace(['purchase_date' => '2026-10-10', 'supplier_name' => 'Proveedor', 'warehouse_location_id' => $this->warehouse, 'status' => 'received', 'tax' => 2, 'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 40], ['product_id' => $this->material->id, 'quantity' => 10, 'unit_cost' => 2]]], $changes);
    }

    private function assertStock(Product $product, float $quantity): void
    {
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'location_id' => $this->warehouse, 'quantity' => $quantity]);
    }

    public function test_sale_sells_products_and_fractional_materials_once_and_cancellation_restores_stock_once(): void
    {
        $response = $this->withHeader('Idempotency-Key', 'sale-one')->postJson('/api/sales', $this->sale())->assertCreated()->assertJsonPath('sale.total', 352)->assertJsonPath('items.0.product_name', 'Bomba 3"');
        $id = $response->json('sale.id');
        $this->postJson('/api/sales', $this->sale())->assertCreated()->assertJsonPath('sale.id', $id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertStock($this->product, 7);
        $this->assertStock($this->material, 18.5);
        $this->getJson('/api/sales/'.$id)->assertOk()->assertJsonCount(2, 'items');
        $this->deleteJson('/api/sales/'.$id)->assertOk()->assertJsonPath('sale.status', 'cancelled');
        $this->deleteJson('/api/sales/'.$id)->assertOk();
        $this->assertStock($this->product, 10);
        $this->assertStock($this->material, 20);
        $this->assertSame(2, DB::table('inventory_movements')->where('movement_type', 'correction')->count());
    }

    public function test_editing_a_completed_sale_replaces_stock_delta_and_insufficient_edit_rolls_back(): void
    {
        $id = $this->postJson('/api/sales', $this->sale())->assertCreated()->json('sale.id');
        $items = [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100]];
        $this->putJson('/api/sales/'.$id, $this->sale(['items' => $items, 'discount' => 0, 'tax' => 0]))->assertOk()->assertJsonPath('sale.total', 200);
        $this->assertStock($this->product, 8);
        $this->assertStock($this->material, 20);
        $items[0]['quantity'] = 99;
        $this->putJson('/api/sales/'.$id, $this->sale(['items' => $items]))->assertUnprocessable();
        $this->assertStock($this->product, 8);
        $this->assertDatabaseHas('operational_sale_items', ['sale_id' => $id, 'quantity' => 2]);
        $this->assertDatabaseHas('sales', ['id' => $id, 'total' => 200]);
    }

    public function test_drafts_do_not_move_stock_until_finalized_and_non_sellable_items_are_rejected(): void
    {
        $id = $this->postJson('/api/sales', $this->sale(['status' => 'draft']))->assertCreated()->json('sale.id');
        $this->assertStock($this->product, 10);
        $this->assertSame(0, DB::table('inventory_movements')->where('movement_type', 'sale')->count());
        $this->putJson('/api/sales/'.$id, $this->sale())->assertOk();
        $this->assertStock($this->product, 7);
        DB::table('products')->where('id', $this->material->id)->update(['is_sellable' => false]);
        $this->postJson('/api/sales', $this->sale())->assertUnprocessable();
        $this->assertStock($this->product, 7);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_protected_stock_and_invalid_lines_cannot_be_sold(): void
    {
        InventoryData::insert('inventory_protection_rules', ['name' => 'Reserva mínima', 'product_id' => $this->product->id, 'attribute_filters' => [], 'protected_quantity' => 10, 'action' => 'protect_stock']);
        $this->postJson('/api/sales', $this->sale())->assertUnprocessable()->assertJsonPath('code', 'INVENTORY_STOCK_INSUFFICIENT');
        $this->postJson('/api/sales', $this->sale(['items' => [['product_id' => $this->product->id, 'quantity' => -1, 'unit_price' => 100]]]))->assertUnprocessable();
        $this->assertStock($this->product, 10);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_registering_a_received_purchase_adds_all_items_once(): void
    {
        $response = $this->withHeader('Idempotency-Key', 'purchase-one')->postJson('/api/purchases', $this->purchase())->assertCreated()->assertJsonPath('purchase.status', 'received')->assertJsonPath('purchase.total', 222);
        $id = $response->json('purchase.id');
        $this->postJson('/api/purchases', $this->purchase())->assertCreated()->assertJsonPath('purchase.id', $id);
        $this->assertStock($this->product, 15);
        $this->assertStock($this->material, 30);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'purchase_cost' => 40]);
    }

    public function test_pending_purchase_can_edit_lines_and_receive_partially_without_double_counting(): void
    {
        $id = $this->postJson('/api/purchases', $this->purchase(['status' => 'ordered']))->assertCreated()->json('purchase.id');
        $this->assertStock($this->product, 10);
        $this->putJson('/api/purchases/'.$id, ['supplier_name' => 'Proveedor actualizado', 'items' => [['product_id' => $this->product->id, 'quantity' => 7, 'unit_cost' => 40]], 'tax' => 0])->assertOk()->assertJsonPath('purchase.total', 280);
        $line = DB::table('operational_purchase_items')->where('purchase_id', $id)->value('id');
        $payload = ['warehouse_location_id' => $this->warehouse, 'items' => [['purchase_item_id' => $line, 'quantity' => 3]]];
        $this->withHeader('Idempotency-Key', 'receive-partial')->postJson('/api/purchases/'.$id.'/receive', $payload)->assertOk()->assertJsonPath('purchase.status', 'partial');
        $this->postJson('/api/purchases/'.$id.'/receive', $payload)->assertOk();
        $this->assertStock($this->product, 13);
        $this->putJson('/api/purchases/'.$id, ['items' => [['product_id' => $this->product->id, 'quantity' => 8, 'unit_cost' => 40]]])->assertUnprocessable();
        $this->putJson('/api/purchases/'.$id, ['supplier_name' => 'Datos corregidos', 'tax' => 2])->assertOk()->assertJsonPath('purchase.total', 282);
        $this->deleteJson('/api/purchases/'.$id)->assertOk()->assertJsonPath('purchase.status', 'cancelled');
        $this->deleteJson('/api/purchases/'.$id)->assertOk();
        $this->assertStock($this->product, 13);
        $this->postJson('/api/purchases/'.$id.'/receive', ['warehouse_location_id' => $this->warehouse, 'items' => [['purchase_item_id' => $line, 'quantity' => 1]]])->assertUnprocessable();
    }

    public function test_legacy_api_urls_share_the_crud_and_unknown_panel_records_return_not_found(): void
    {
        $sale = $this->postJson('/api/sale', $this->sale(['status' => 'draft']))->assertCreated()->json('sale.id');
        $this->getJson('/api/sale/'.$sale)->assertOk()->assertJsonPath('sale.id', $sale);
        $this->putJson('/api/sale/'.$sale, $this->sale())->assertOk()->assertJsonPath('sale.status', 'completed');
        $this->deleteJson('/api/sale/'.$sale)->assertOk()->assertJsonPath('sale.status', 'cancelled');
        $this->assertStock($this->product, 10);

        $purchase = $this->postJson('/api/purchase', $this->purchase(['status' => 'ordered']))->assertCreated()->json('purchase.id');
        $this->getJson('/api/purchase/'.$purchase)->assertOk()->assertJsonPath('purchase.id', $purchase);
        $this->putJson('/api/purchase/'.$purchase, ['supplier_name' => 'Proveedor corregido'])->assertOk()->assertJsonPath('purchase.supplier_name', 'Proveedor corregido');
        $this->deleteJson('/api/purchase/'.$purchase)->assertOk()->assertJsonPath('purchase.status', 'cancelled');
        $this->assertStock($this->product, 10);
        $this->actingAs($this->admin)->withoutVite();
        foreach (['/panel/sales/99999', '/panel/sales/99999/edit', '/panel/purchases/99999', '/panel/purchases/99999/edit'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_view_only_permissions_do_not_allow_opening_mutation_forms_or_using_legacy_api_mutations(): void
    {
        DB::table('roles')->insert(['id' => 2, 'name' => 'management', 'description' => 'Dirección']);
        $user = User::factory()->create(['role_id' => 2, 'phone' => '3129999999']);
        $this->actingAs($user)->withoutVite();
        $this->get('/panel/sales')->assertOk();
        $this->get('/panel/purchases')->assertOk();
        foreach (['/panel/sales/create', '/panel/sales/1/edit', '/panel/purchases/create', '/panel/purchases/1/edit'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->actingAs($user, 'api');
        $this->postJson('/api/sale', $this->sale())->assertForbidden();
        $this->postJson('/api/purchase', $this->purchase())->assertForbidden();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertStock($this->product, 10);
    }

    public function test_sale_permissions_pagination_and_panel_crud_routes(): void
    {
        $id = $this->postJson('/api/sales', $this->sale(['status' => 'draft']))->assertCreated()->json('sale.id');
        $this->getJson('/api/sales?status=draft&q=mostrador')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($this->admin)->withoutVite();
        foreach (['/panel/sales', '/panel/sales/create', '/panel/sales/'.$id, '/panel/sales/'.$id.'/edit', '/panel/purchases', '/panel/purchases/create'] as $path) {
            $this->get($path)->assertOk()->assertViewIs('panel.commerce.index');
        }
        DB::table('roles')->insert(['id' => 2, 'name' => 'technician', 'description' => 'Técnico']);
        $user = User::factory()->create(['role_id' => 2, 'phone' => '3129999999']);
        $this->actingAs($user, 'api')->postJson('/api/sales', $this->sale())->assertForbidden();
        $this->getJson('/api/sales')->assertForbidden();
        $this->assertStock($this->product, 10);
    }
}
