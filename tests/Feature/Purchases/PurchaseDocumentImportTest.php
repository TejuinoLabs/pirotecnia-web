<?php

namespace Tests\Feature\Purchases;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\ProductCatalogService;
use App\Services\Purchases\PurchaseDocumentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseDocumentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->actingAs($this->admin, 'api');
    }

    private function file(): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/purchases/budget.pdf'), 'presupuesto.pdf', 'application/pdf', null, true);
    }

    private function products(): array
    {
        return array_map(fn ($item) => ['product_id' => app(ProductCatalogService::class)->save(['name' => $item['description'], 'product_role_id' => 1, 'unit' => 'pz'], $this->admin->id)['id']], app(PurchaseDocumentParser::class)->read($this->file()->getRealPath())['items']);
    }

    public function test_reads_the_sample_and_matches_only_unambiguous_active_catalog_products(): void
    {
        $product = app(ProductCatalogService::class)->save(['name' => 'Quark', 'product_role_id' => 1, 'unit' => 'pz'], $this->admin->id);
        $preview = $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()
            ->assertJsonPath('purchase_date', '2026-10-17')->assertJsonPath('supplier_name', null)
            ->assertJsonPath('total', 88204.6)->assertJsonCount(11, 'items')->assertJsonPath('items.0.product_id', $product['id'])
            ->assertJsonPath('items.1.product_id', null)->assertJsonPath('items.8.unit_cost', 620.4);
        $this->assertEquals(152, array_sum(array_column($preview->json('items'), 'quantity')));
        $this->assertDatabaseCount('purchases', 0);
        Storage::disk('local')->assertExists(DB::table('purchase_document_imports')->value('path'));
        Product::whereKey($product['id'])->update(['active' => false]);
        $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()->assertJsonPath('items.0.product_id', null)->assertJsonPath('token', $preview->json('token'));
        $this->assertDatabaseCount('purchase_document_imports', 1);
    }

    public function test_registration_keeps_extracted_amounts_and_retries_do_not_duplicate_stock(): void
    {
        $items = $this->products();
        $location = InventoryData::insert('inventory_locations', ['name' => 'Principal', 'code' => 'MAIN', 'type' => 'warehouse']);
        $token = $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()->json('token');
        $items[0]['quantity'] = 999;
        $items[0]['unit_cost'] = 0;
        $payload = ['token' => $token, 'supplier_name' => 'Proveedor confirmado', 'status' => 'received', 'warehouse_location_id' => $location, 'items' => $items];
        $purchase = $this->postJson('/api/purchases/documents/import', $payload)->assertCreated()->assertJsonPath('purchase.total', 88204.6)->assertJsonPath('purchase.status', 'received')->json('purchase.id');
        $this->postJson('/api/purchases/documents/import', $payload)->assertCreated()->assertJsonPath('purchase.id', $purchase);
        $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()->assertJsonPath('purchase_id', $purchase);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $items[0]['product_id'], 'location_id' => $location, 'quantity' => 24]);
        $this->assertDatabaseHas('operational_purchase_items', ['purchase_id' => $purchase, 'product_id' => $items[0]['product_id'], 'quantity' => 24, 'unit_cost' => 427]);
    }

    public function test_pending_import_requires_supplier_and_all_catalog_mappings_and_does_not_move_stock(): void
    {
        $items = $this->products();
        $token = $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()->json('token');
        $payload = ['token' => $token, 'status' => 'ordered', 'items' => $items];
        $this->postJson('/api/purchases/documents/import', $payload)->assertUnprocessable()->assertJsonValidationErrors('supplier_name');
        $payload['supplier_name'] = 'Proveedor';
        $missing = $payload;
        array_pop($missing['items']);
        $this->postJson('/api/purchases/documents/import', $missing)->assertUnprocessable();
        $this->assertDatabaseCount('purchases', 0);
        $this->postJson('/api/purchases/documents/import', $payload)->assertCreated()->assertJsonPath('purchase.status', 'ordered');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_document_routes_require_purchase_creation_permission_and_validate_files(): void
    {
        $this->postJson('/api/purchases/documents/preview', ['document' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')])->assertUnprocessable();
        $this->postJson('/api/purchases/documents/preview', ['document' => UploadedFile::fake()->createWithContent('broken.pdf', '%PDF-1.4 invalid')])->assertUnprocessable();
        DB::table('roles')->insert(['id' => 2, 'name' => 'management', 'description' => 'Dirección']);
        $user = User::factory()->create(['role_id' => 2, 'phone' => '3129999999']);
        $this->actingAs($user, 'api');
        $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertForbidden();
        $this->postJson('/api/purchases/documents/import', [])->assertForbidden();
        $this->assertDatabaseCount('purchase_document_imports', 0);
    }

    public function test_rejects_inconsistent_totals_and_unreadable_formats(): void
    {
        $text = (new \Smalot\PdfParser\Parser)->parseFile($this->file()->getRealPath())->getText();
        foreach ([str_replace('88,204.60', '88,204.61', $text), str_replace('10,248.00', '10,249.00', $text), str_replace('Productos: 11', 'Productos: 12', $text), 'Documento escaneado'] as $invalid) {
            try {
                app(PurchaseDocumentParser::class)->parse($invalid);
                $this->fail('Expected a validation error.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }
    }

    public function test_purchasing_can_create_and_select_a_product_without_leaving_the_import_and_retries_reuse_it(): void
    {
        DB::table('roles')->insert(['id' => 3, 'name' => 'purchasing', 'description' => 'Compras']);
        $buyer = User::factory()->create(['role_id' => 3, 'phone' => '3128888888']);
        $this->actingAs($buyer, 'api');
        $preview = $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()->json();
        $items = [];
        foreach ($preview['items'] as $index => $item) {
            $payload = ['token' => $preview['token'], 'line_index' => $index, 'name' => $item['description'], 'unit' => 'pz', 'product_role_id' => 1];
            $product = $this->postJson('/api/purchases/documents/products', $payload)->assertCreated()->assertJsonPath('name', $item['description'])->json();
            $this->assertEquals($item['unit_cost'], $product['purchase_cost']);
            $this->postJson('/api/purchases/documents/products', [...$payload, 'name' => '  '.mb_strtolower($item['description']).'  '])->assertCreated()->assertJsonPath('id', $product['id']);
            $items[] = ['product_id' => $product['id']];
        }
        $this->assertDatabaseCount('products', 11);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->postJson('/api/purchases/documents/import', ['token' => $preview['token'], 'supplier_name' => 'Proveedor', 'status' => 'ordered', 'items' => $items])->assertCreated()->assertJsonPath('purchase.total', 88204.6);
        $this->postJson('/api/purchases/documents/products', $payload)->assertUnprocessable();
    }

    public function test_inline_creation_requires_a_pending_document_valid_line_and_purchase_permission(): void
    {
        $token = $this->postJson('/api/purchases/documents/preview', ['document' => $this->file()])->assertOk()->json('token');
        $payload = ['token' => $token, 'line_index' => 99, 'name' => 'Nuevo', 'unit' => 'pz', 'product_role_id' => 1];
        $this->postJson('/api/purchases/documents/products', $payload)->assertUnprocessable();
        $this->postJson('/api/purchases/documents/products', [...$payload, 'line_index' => 0, 'name' => ' '])->assertUnprocessable();
        $this->postJson('/api/purchases/documents/products', [...$payload, 'line_index' => 0, 'product_role_id' => 999])->assertUnprocessable();
        $this->postJson('/api/purchases/documents/products', [...$payload, 'line_index' => 0, 'token' => (string) \Illuminate\Support\Str::uuid()])->assertNotFound();
        DB::table('roles')->insert(['id' => 2, 'name' => 'management', 'description' => 'Dirección']);
        $user = User::factory()->create(['role_id' => 2, 'phone' => '3129999999']);
        $this->actingAs($user, 'api')->postJson('/api/purchases/documents/products', [...$payload, 'line_index' => 0])->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }
}
