<?php

namespace Tests\Feature\Inventory;

use App\Models\Event;
use App\Models\Inventory;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\EventCoverageService;
use App\Services\Inventory\EventDispatchService;
use App\Services\Inventory\EventPickingService;
use App\Services\Inventory\EventPreparationService;
use App\Services\Inventory\EventRequirementService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryException;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\PackageRequirementService;
use App\Services\Inventory\ProductCatalogService;
use App\Services\Inventory\ProductMatcherService;
use App\Services\Inventory\PurchaseReceiptService;
use App\Services\Inventory\RequirementAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $warehouse;

    private int $category;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->warehouse = InventoryData::insert('inventory_locations', ['name' => 'Almacén', 'code' => 'MAIN', 'type' => 'warehouse']);
        $this->category = InventoryData::insert('categories', ['name' => 'Cake', 'code' => 'cake']);
        InventoryData::insert('attribute_definitions', ['code' => 'shots', 'name' => 'Disparos', 'data_type' => 'number']);
        $this->package = Package::create(['name' => 'Show', 'description' => 'Show', 'price' => 5000, 'duration' => '15 min']);
    }

    private function product(string $name = 'Alien', float $value = 2400): Product
    {
        $data = app(ProductCatalogService::class)->save(['name' => $name, 'product_role_id' => 1, 'unit' => 'pz', 'category_id' => $this->category, 'reference_value' => $value, 'attributes' => ['shots' => 100]], $this->admin->id);

        return Product::findOrFail($data['id']);
    }

    private function stock(Product $product, float $quantity = 10): void
    {
        app(InventoryMovementService::class)->record($product->id, $quantity, null, $this->warehouse, 'adjustment_in', $this->admin->id, 'Apertura');
    }

    private function event(float $quantity = 3): Event
    {
        if (! DB::table('package_requirements')->where('package_id', $this->package->id)->exists()) {
            app(PackageRequirementService::class)->create($this->package->id, ['requirement_type' => 'specification', 'category_id' => $this->category, 'quantity' => $quantity, 'reference_value' => 2500, 'value_tolerance' => 500, 'constraints' => [['attribute_code' => 'shots', 'operator' => '=', 'value' => 100, 'is_hard_constraint' => true]]], $this->admin->id);
        }
        $event = Event::create(['package_id' => $this->package->id, 'date' => now()->toDateString(), 'phone' => '3121234567', 'client_name' => 'Cliente', 'event_address' => 'Salón', 'event_date' => now()->addDays(10)]);
        $event->packages()->attach($this->package->id, ['quantity' => 1, 'price' => 5000]);
        app(EventRequirementService::class)->snapshot($event, $this->admin->id);

        return $event;
    }

    private function requirement(Event $event): int
    {
        return DB::table('event_requirements')->where('event_id', $event->id)->value('id');
    }

    private function allocation(Event $event, Product $product, float $quantity = 3): array
    {
        return app(RequirementAllocationService::class)->allocate($event->id, $this->requirement($event), ['warehouse_location_id' => $this->warehouse, 'allocations' => [['product_id' => $product->id, 'quantity' => $quantity]]], $this->admin);
    }

    private function dispatch(Event $event, Product $product): array
    {
        $this->allocation($event, $product);
        $experience = InventoryData::insert('experience_levels', ['name' => 'Técnico', 'description' => 'Técnico']);
        $employee = InventoryData::insert('employees', ['name' => 'Técnico', 'email' => 'technician@example.com', 'phone' => '3121111111', 'salary' => '100', 'experience_level_id' => $experience, 'user_id' => $this->admin->id]);
        DB::table('employee_event')->insert(['event_id' => $event->id, 'employee_id' => $employee]);
        $picking = app(EventPickingService::class)->create($event->id, $this->warehouse, $this->admin->id);
        foreach ($picking['items'] as $item) {
            app(EventPickingService::class)->confirm($event->id, $picking['picking']['id'], $item->id, (float) $item->quantity_required, $this->admin->id);
        }
        app(EventPickingService::class)->complete($event->id, $picking['picking']['id'], $this->admin->id);
        app(EventPreparationService::class)->transition($event->id, true, $this->admin->id);

        return app(EventDispatchService::class)->create($event->id, ['picking_id' => $picking['picking']['id'], 'technician_id' => $this->admin->id], $this->admin->id);
    }

    public function test_partial_purchase_receipts_add_stock_and_over_receipts_roll_back(): void
    {
        $product = $this->product();
        $purchase = app(PurchaseReceiptService::class)->create(['purchase_date' => now()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1650]]], $this->admin->id);
        $id = $purchase['purchase']['id'];
        $line = $purchase['items'][0]->id;
        $this->actingAs($this->admin, 'api')->postJson("/api/purchases/$id/receive", ['warehouse_location_id' => $this->warehouse, 'items' => [['purchase_item_id' => $line, 'quantity' => 4]]], ['Idempotency-Key' => 'purchase-1'])->assertOk()->assertJsonPath('purchase.status', 'partial');
        $this->postJson("/api/purchases/$id/receive", ['warehouse_location_id' => $this->warehouse, 'items' => [['purchase_item_id' => $line, 'quantity' => 7]]])->assertUnprocessable();
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'quantity' => 4]);
        $this->postJson("/api/purchases/$id/receive", ['warehouse_location_id' => $this->warehouse, 'items' => [['purchase_item_id' => $line, 'quantity' => 6]]])->assertOk()->assertJsonPath('purchase.status', 'received');
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'quantity' => 10]);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_event_creation_keeps_physical_stock_and_snapshot_is_immutable(): void
    {
        $product = $this->product();
        $this->stock($product);
        $event = $this->event();
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'quantity' => 10]);
        DB::table('package_requirements')->where('package_id', $this->package->id)->update(['quantity' => 20]);
        app(EventRequirementService::class)->snapshot($event, $this->admin->id);
        $this->assertDatabaseCount('event_requirements', 1);
        $this->assertDatabaseHas('event_requirements', ['event_id' => $event->id, 'quantity_required' => 3]);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_candidates_detect_new_compatible_skus_and_exclude_wrong_value_and_shots(): void
    {
        $alien = $this->product();
        $infinity = $this->product('Infinity', 3500);
        $this->stock($alien);
        $this->stock($infinity);
        $event = $this->event();
        $requirement = $this->requirement($event);
        $this->actingAs($this->admin, 'api')->getJson("/api/events/{$event->id}/requirements/$requirement/candidates?location_id={$this->warehouse}&include_protected=true")->assertOk()->assertJsonFragment(['name' => 'Infinity', 'status' => 'incompatible']);
        $galaxy = $this->product('Galaxy', 2550);
        $this->stock($galaxy);
        $data = $this->getJson("/api/events/{$event->id}/requirements/$requirement/candidates?location_id={$this->warehouse}")->assertOk()->json('candidates');
        $this->assertSame('recommended', collect($data)->firstWhere('product_id', $galaxy->id)['status']);
        app(ProductCatalogService::class)->save(['attributes' => ['shots' => 99]], $this->admin->id, $galaxy->id);
        $candidates = app(ProductMatcherService::class)->candidates(InventoryData::get('events', $event->id), InventoryData::get('event_requirements', $requirement), $this->warehouse);
        $this->assertFalse(collect($candidates)->firstWhere('product_id', $galaxy->id)['compatible']);
    }

    public function test_protection_requires_permission_and_reason_and_records_override(): void
    {
        $product = $this->product();
        $this->stock($product, 8);
        $event = $this->event();
        InventoryData::insert('inventory_protection_rules', ['name' => 'Reservar', 'product_id' => $product->id, 'attribute_filters' => [], 'protected_quantity' => 6, 'action' => 'protect_stock']);
        $id = $this->requirement($event);
        $payload = ['warehouse_location_id' => $this->warehouse, 'allocations' => [['product_id' => $product->id, 'quantity' => 3]]];
        $this->actingAs($this->admin, 'api')->postJson("/api/events/{$event->id}/requirements/$id/allocations", $payload)->assertUnprocessable()->assertJsonPath('code', 'ALLOCATION_OVERRIDE_REQUIRED');
        $this->assertDatabaseCount('requirement_allocations', 0);
        $this->postJson("/api/events/{$event->id}/requirements/$id/allocations", $payload + ['override_reason' => 'Autorizado para este espectáculo'])->assertOk();
        $this->assertDatabaseCount('allocation_rule_overrides', 1);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'quantity' => 8]);
    }

    public function test_shared_compatible_stock_is_not_double_counted_in_coverage(): void
    {
        $product = $this->product();
        $this->stock($product, 4);
        $first = $this->event();
        $second = $this->event();
        $coverage = app(EventCoverageService::class)->coverage($second->id);
        $this->assertSame('critical', $coverage['status']);
        $this->assertSame(2.0, $coverage['requirements'][0]['deficit']);
        $this->assertDatabaseHas('inventory_alerts', ['event_id' => $second->id, 'severity' => 'critical']);
    }

    public function test_allocations_reserve_without_moving_and_cannot_overcommit_stock(): void
    {
        $product = $this->product();
        $this->stock($product, 4);
        $first = $this->event();
        $second = $this->event();
        $this->allocation($first, $product);
        $this->assertDatabaseHas('inventory_balances', ['quantity' => 4]);
        $payload = ['warehouse_location_id' => $this->warehouse, 'allocations' => [['product_id' => $product->id, 'quantity' => 3]]];
        $this->actingAs($this->admin, 'api')->postJson('/api/events/'.$second->id.'/requirements/'.$this->requirement($second).'/allocations', $payload)->assertUnprocessable()->assertJsonPath('code', 'INVENTORY_STOCK_INSUFFICIENT');
        $this->assertDatabaseCount('requirement_allocations', 1);
    }

    public function test_confirmed_dispatch_moves_stock_once_and_retry_is_idempotent(): void
    {
        $product = $this->product();
        $this->stock($product);
        $event = $this->event();
        $dispatch = $this->dispatch($event, $product);
        $id = $dispatch['dispatch']['id'];
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $this->warehouse, 'quantity' => 10]);
        $this->actingAs($this->admin, 'api')->postJson("/api/event-dispatches/$id/confirm", [], ['Idempotency-Key' => 'dispatch-1'])->assertOk();
        $this->postJson("/api/event-dispatches/$id/confirm", [], ['Idempotency-Key' => 'dispatch-1'])->assertOk();
        $this->postJson("/api/event-dispatches/$id/confirm")->assertUnprocessable()->assertJsonPath('code', 'DISPATCH_ALREADY_CONFIRMED');
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $this->warehouse, 'quantity' => 7]);
        Inventory::updateQuantityProducts($event);
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $this->warehouse, 'quantity' => 7]);
    }

    public function test_technician_receipt_ownership_payload_and_retry(): void
    {
        $product = $this->product();
        $this->stock($product);
        $event = $this->event();
        $dispatch = $this->dispatch($event, $product);
        $id = $dispatch['dispatch']['id'];
        $dispatcher = User::factory()->create(['role_id' => 1, 'phone' => '3121111122']);
        app(EventDispatchService::class)->confirm($id, $dispatcher->id);
        $other = User::factory()->create(['phone' => '3121111111']);
        $payload = ['expected_version' => DB::table('event_dispatches')->where('id', $id)->value('version'), 'items' => [['dispatch_item_id' => $dispatch['items'][0]->id, 'received_quantity' => '3.000', 'status' => 'received']]];
        $this->actingAs($other, 'api')->postJson("/api/technician/dispatches/$id/receive", $payload)->assertForbidden();
        $this->actingAs($this->admin, 'api')->postJson("/api/technician/dispatches/$id/receive", $payload, ['Idempotency-Key' => 'receipt-1'])->assertOk();
        $this->postJson("/api/technician/dispatches/$id/receive", $payload, ['Idempotency-Key' => 'receipt-1'])->assertOk();
        $this->postJson("/api/technician/dispatches/$id/receive", ['items' => []], ['Idempotency-Key' => 'receipt-1'])->assertUnprocessable();
        $this->assertDatabaseCount('technician_receipts', 1);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'logistics_status' => 'in_operation']);
    }

    public function test_return_reconciles_leftovers_damage_loss_and_closes_event(): void
    {
        $product = $this->product();
        $this->stock($product);
        $event = $this->event();
        $dispatch = $this->dispatch($event, $product);
        $id = $dispatch['dispatch']['id'];
        app(EventDispatchService::class)->confirm($id, $this->admin->id);
        $damaged = InventoryData::insert('inventory_locations', ['name' => 'Daños', 'code' => 'DAMAGED', 'type' => 'damaged']);
        $payload = ['event_dispatch_id' => $id, 'warehouse_location_id' => $this->warehouse, 'damaged_location_id' => $damaged, 'items' => [['dispatch_item_id' => $dispatch['items'][0]->id, 'quantity_consumed' => 0, 'quantity_returned' => 1, 'quantity_damaged' => 1, 'quantity_lost' => 1]]];
        $this->actingAs($this->admin, 'api')->postJson("/api/events/{$event->id}/returns", $payload)->assertOk();
        $returnId = DB::table('event_returns')->value('id');
        $this->postJson("/api/event-returns/$returnId/confirm", [], ['Idempotency-Key' => 'return-1'])->assertOk();
        $this->postJson("/api/event-returns/$returnId/confirm", [], ['Idempotency-Key' => 'return-1'])->assertOk();
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $this->warehouse, 'quantity' => 8]);
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $damaged, 'quantity' => 1]);
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $dispatch['dispatch']['destination_location_id'], 'quantity' => 0]);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'logistics_status' => 'closed']);
        $this->assertDatabaseCount('inventory_movements', 5);
    }

    public function test_adjustment_and_inverse_leave_immutable_audited_ledger(): void
    {
        $product = $this->product();
        $this->stock($product, 5);
        $this->actingAs($this->admin, 'api')->postJson('/api/inventory/adjustments', ['product_id' => $product->id, 'location_id' => $this->warehouse, 'direction' => 'out', 'quantity' => 6, 'notes' => 'Conteo'])->assertUnprocessable()->assertJsonPath('code', 'INVENTORY_STOCK_INSUFFICIENT');
        $id = DB::table('inventory_movements')->value('id');
        $this->postJson("/api/inventory/movements/$id/reverse", ['reason' => 'Corrección de apertura'])->assertOk();
        $this->postJson("/api/inventory/movements/$id/reverse", ['reason' => 'Otra corrección'])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_balances', ['quantity' => 0]);
        $this->getJson('/api/inventory/movements?location_id='.$this->warehouse)->assertOk()->assertJsonPath('data.0.balance', 0);
        $this->deleteJson("/api/inventory/movements/$id")->assertNotFound();
        $this->assertDatabaseHas('inventory_audits', ['action' => 'correction']);
    }

    public function test_catalog_routes_and_panel_have_permission_checks(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/inventory/catalogs/colors', ['name' => 'Azul', 'code' => 'blue'])->assertOk();
        $id = DB::table('colors')->value('id');
        $this->getJson('/api/inventory/catalogs/colors/'.$id)->assertOk()->assertJsonPath('code', 'blue');
        $this->putJson('/api/inventory/catalogs/colors/'.$id, ['name' => 'Azul brillante'])->assertOk();
        $this->deleteJson('/api/inventory/catalogs/colors/'.$id)->assertOk();
        $user = User::factory()->create(['phone' => '3122222222']);
        $this->actingAs($user, 'api')->getJson('/api/inventory')->assertForbidden();
        $this->actingAs($this->admin)->withoutVite()->get('/panel/inventories')->assertOk()->assertViewHas('area', 'inventory')->assertDontSee('Almacén y logística');
        $this->get('/inventory')->assertRedirect(route('inventories.index'));
        $this->get('/inventory/catalogs')->assertRedirect(route('settings.index'));
        $this->get('/inventory/purchases')->assertRedirect(route('purchases.index'));
        $this->get('/inventory/preparations')->assertRedirect(route('inventory.preparations'));
        foreach (['categories', 'attributes', 'effects', 'colors', 'locations', 'profiles'] as $catalog) {
            $this->get('/settings/inventory/'.$catalog)->assertOk()->assertViewHas('area', 'settings')->assertViewHas('selectedCatalog', $catalog);
        }
        $this->getJson('/inventory/operations/inventory')->assertOk();
        foreach (['/inventory/kardex', '/panel/purchases', '/settings/package-requirements', '/panel/events/preparations', '/inventory/audit', '/settings/inventory-rules', '/settings/products'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_catalog_and_stock_lists_paginate_without_losing_filters(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->product('Producto '.$i);
            InventoryData::insert('colors', ['name' => 'Color '.$i, 'code' => 'color-'.$i]);
        }
        $this->actingAs($this->admin, 'api')->getJson('/api/inventory?category_id='.$this->category)
            ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('total', 16)->assertJsonPath('last_page', 2);
        $this->getJson('/api/inventory?category_id='.$this->category.'&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('current_page', 2);
        $this->getJson('/api/inventory/catalogs/colors?page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('total', 16);
        $this->actingAs($this->admin)->withoutVite()->get('/settings/packages')->assertOk()->assertSee('Paginación del listado');
        $this->get('/settings')->assertOk()->assertSee('Categorías')->assertSee('Ubicaciones');
    }

    public function test_existing_lists_render_pagination_and_search_keeps_its_query(): void
    {
        $this->actingAs($this->admin)->withoutVite();
        foreach (['settings.equipments.index', 'settings.client-types.index', 'settings.event_types.index', 'settings.experience-levels.index', 'events.index', 'employees.index', 'users.index', 'sales.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Paginación del listado');
        }
        for ($i = 0; $i < 16; $i++) {
            Package::create(['name' => 'Encontrar '.$i, 'description' => 'Show', 'price' => 100]);
        }
        $this->get('/panel/search?q=Encontrar&packages_page=2')->assertOk()
            ->assertViewHas('resultGroups', function ($groups) {
                $packages = $groups->firstWhere('key', 'packages')['items'];

                return $packages->total() === 16 && $packages->count() === 1 && $packages->currentPage() === 2
                    && str_contains($packages->previousPageUrl(), 'q=Encontrar');
            });
    }

    public function test_settings_catalogs_share_presentation_and_keep_inventory_separate(): void
    {
        $this->actingAs($this->admin)->withoutVite();
        foreach (['/settings/products', '/settings/inventory/categories', '/settings/inventory/attributes', '/settings/inventory/effects', '/settings/inventory/colors', '/settings/inventory/locations', '/settings/inventory/profiles', '/settings/inventory-rules', '/settings/package-requirements'] as $uri) {
            $this->get($uri)->assertOk()->assertViewIs('panel.settings.inventory-catalog')
                ->assertSee('data-settings-header', false)->assertSee('data-settings-stats', false)->assertSee('id="inventory-actions"', false);
        }
        foreach (['/settings/client-types', '/settings/experience-levels', '/settings/event-types'] as $uri) {
            $this->get($uri)->assertOk()->assertSee('data-settings-header', false)->assertSee('data-settings-stats', false)->assertDontSee('class="content-header"', false);
        }
        $this->get('/panel/inventories')->assertOk()->assertViewIs('panel.inventory.operations');
    }

    public function test_experience_and_event_type_forms_render_save_and_preserve_used_records(): void
    {
        $this->actingAs($this->admin)->withoutVite();
        foreach (['experience-levels' => ['experience_levels', 'experience_level_id'], 'event_types' => ['event_types', 'event_type_id']] as $path => [$table, $foreign]) {
            $id = DB::table($table)->insertGetId(['name' => 'Inicial', 'description' => 'Descripción inicial']);
            $this->get('/settings/'.$path.'/create')->assertOk()->assertSee('Nombre')->assertDontSee('form-control');
            $this->get('/settings/'.$path.'/'.$id)->assertOk()->assertSee('Descripción inicial');
            $this->get('/settings/'.$path.'/'.$id.'/edit')->assertOk()->assertSee('Descripción inicial');
            $this->put('/settings/'.$path.'/'.$id, ['name' => 'Actualizado', 'description' => 'Descripción actualizada'])->assertRedirect();
            $this->assertDatabaseHas($table, ['id' => $id, 'name' => 'Actualizado']);
            $this->package->forceFill([$foreign => $id])->save();
            $this->delete('/settings/'.$path.'/'.$id)->assertRedirect()->assertSessionHas('error');
            $this->assertDatabaseHas($table, ['id' => $id]);
        }
    }

    public function test_inventory_distinguishes_bombs_by_caliber_without_renaming_records(): void
    {
        $bomb = $this->product('Bomba');
        $other = $this->product('Bomba');
        DB::table('products')->where('id', $bomb->id)->update(['caliber' => '3']);
        DB::table('products')->where('id', $other->id)->update(['caliber' => '4']);
        $this->stock($bomb);
        $this->actingAs($this->admin, 'api')->getJson('/api/inventory')->assertOk()
            ->assertJsonPath('data.0.display_name', 'Bomba 3"')->assertJsonPath('data.1.display_name', 'Bomba 4"');
        $this->getJson('/api/inventory/movements')->assertOk()->assertJsonPath('data.0.product_name', 'Bomba 3"');
        $this->actingAs($this->admin)->withoutVite()->get('/panel/inventories')->assertOk()
            ->assertViewHas('catalogs', fn ($catalogs) => collect($catalogs['products'])->pluck('name')->all() === ['Bomba 3"', 'Bomba 4"']);
        $this->assertSame('Bomba', $bomb->fresh()->name);
        $this->assertSame('Bomba 3"', (new Product(['name' => 'Bomba 3"', 'caliber' => '3']))->display_name);
        $this->assertSame('Bomba 3"', (new Product(['name' => 'Bomba', 'caliber' => '3.000']))->display_name);
        $this->assertSame('Bomba 20 mm', (new Product(['name' => 'Bomba', 'caliber' => '20 mm']))->display_name);
    }

    public function test_inventory_quantities_hide_trailing_zeroes_and_keep_fractions(): void
    {
        $product = $this->product();
        DB::table('products')->where('id', $product->id)->update(['minimum_stock' => 3]);
        $this->stock($product, 1.25);
        $this->actingAs($this->admin)->withoutVite()->get('/panel/inventories')->assertOk()
            ->assertDontSee('0.000')->assertSee('1.25')->assertDontSee('1.250');
    }

    public function test_migration_resumes_partial_schema_and_preserves_existing_data(): void
    {
        $migration = require database_path('migrations/2026_10_07_000000_create_operational_inventory.php');
        $migration->down();
        \Illuminate\Support\Facades\Schema::create('categories', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        DB::table('categories')->insert(['name' => 'Existente', 'code' => 'existing']);
        \Illuminate\Support\Facades\Schema::table('products', fn ($table) => $table->boolean('active')->default(false));
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('categories', ['name' => 'Existente', 'code' => 'existing']);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('products', ['active', 'is_event_usable', 'minimum_stock']));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('purchases', ['expected_date', 'uses_operational_inventory']));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('incorporó tablas existentes');
        $migration->down();
    }

    public function test_explicit_legacy_opening_import_is_not_repeated(): void
    {
        $product = $this->product();
        $inventory = new Inventory;
        $inventory->forceFill(['name' => 'Anterior', 'location' => 'Bodega'])->save();
        $inventory->products()->attach($product->id, ['quantity' => 15, 'price' => 100]);
        $location = InventoryData::insert('inventory_locations', ['name' => 'Importado', 'code' => 'LEGACY', 'type' => 'warehouse', 'legacy_inventory_id' => $inventory->id]);
        app(InventoryMovementService::class)->adopt($location, $this->admin->id);
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $location, 'quantity' => 15]);
        $this->expectException(InventoryException::class);
        app(InventoryMovementService::class)->adopt($location, $this->admin->id);
    }

    public function test_api_creates_a_generic_event_even_when_inventory_is_missing(): void
    {
        app(PackageRequirementService::class)->create($this->package->id, ['requirement_type' => 'specification', 'category_id' => $this->category, 'quantity' => 1, 'constraints' => [['attribute_code' => 'shots', 'operator' => '=', 'value' => 100]]], $this->admin->id);
        $this->actingAs($this->admin, 'api')->postJson('/api/events', ['date' => now()->toDateString(), 'event_date' => now()->addDays(2)->toDateTimeString(), 'phone' => '3121111111', 'client_name' => 'Cliente sin stock', 'event_address' => 'Salón', 'package_id' => $this->package->id])->assertCreated()->assertJsonPath('coverage.status', 'critical');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('event_requirements', 1);
    }

    public function test_profile_constraints_are_snapshotted_and_new_profile_edits_do_not_change_event(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/inventory/catalogs/profiles', ['name' => 'Cake 100', 'category_id' => $this->category, 'constraints' => [['attribute_code' => 'shots', 'operator' => '=', 'value' => 100]]])->assertOk();
        $profile = $response->json('id');
        app(PackageRequirementService::class)->create($this->package->id, ['requirement_type' => 'profile', 'profile_id' => $profile, 'quantity' => 1], $this->admin->id);
        $event = $this->event(1);
        $requirement = InventoryData::get('event_requirements', $this->requirement($event));
        $this->putJson('/api/inventory/catalogs/profiles/'.$profile, ['constraints' => [['attribute_code' => 'shots', 'operator' => '=', 'value' => 200]]])->assertOk();
        $product = $this->product();
        $this->stock($product);
        $this->assertTrue(app(ProductMatcherService::class)->compatible($product, $requirement));
    }

    public function test_configured_event_type_exception_unprotects_stock_without_override(): void
    {
        $type = InventoryData::insert('event_types', ['name' => 'Uso especial', 'description' => 'Evento autorizado']);
        $product = $this->product();
        $this->stock($product, 8);
        $event = $this->event();
        $event->update(['event_type_id' => $type]);
        InventoryData::insert('inventory_protection_rules', ['name' => 'Reservar', 'product_id' => $product->id, 'attribute_filters' => [], 'protected_quantity' => 6, 'excluded_event_types' => [$type], 'action' => 'protect_stock']);
        $this->allocation($event, $product);
        $this->assertDatabaseCount('allocation_rule_overrides', 0);
        $this->assertDatabaseCount('requirement_allocations', 1);
    }

    public function test_warehouse_user_cannot_override_even_with_a_reason(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'warehouse', 'description' => 'Almacén']);
        $user = User::factory()->create(['role_id' => $role, 'phone' => '3122222222']);
        $product = $this->product();
        $this->stock($product, 8);
        $event = $this->event();
        InventoryData::insert('inventory_protection_rules', ['name' => 'Reservar', 'product_id' => $product->id, 'attribute_filters' => [], 'protected_quantity' => 6, 'action' => 'protect_stock']);
        $this->actingAs($user, 'api')->postJson('/api/events/'.$event->id.'/requirements/'.$this->requirement($event).'/allocations', ['warehouse_location_id' => $this->warehouse, 'allocations' => [['product_id' => $product->id, 'quantity' => 3]], 'override_reason' => 'No autorizado'])->assertUnprocessable()->assertJsonPath('code', 'ALLOCATION_OVERRIDE_REQUIRED');
        $this->assertDatabaseCount('requirement_allocations', 0);
    }

    public function test_generic_future_demand_is_distributed_across_compatible_skus(): void
    {
        $first = $this->product();
        $second = $this->product('Galaxy');
        $this->stock($first, 4);
        $this->stock($second, 2);
        $this->event(5);
        $response = $this->actingAs($this->admin, 'api')->getJson('/api/inventory')->assertOk()->json('data');
        $this->assertEquals(5, collect($response)->sum('future_demand'));
        $this->assertEquals(4, collect($response)->firstWhere('id', $first->id)['future_demand']);
        $this->assertEquals(1, collect($response)->firstWhere('id', $second->id)['future_demand']);
    }

    public function test_purchase_expected_after_event_does_not_cover_its_deficit(): void
    {
        $product = $this->product();
        $event = $this->event();
        app(PurchaseReceiptService::class)->create(['purchase_date' => now()->toDateString(), 'expected_date' => now()->addDays(20)->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 100]]], $this->admin->id);
        $this->assertSame('critical', app(EventCoverageService::class)->coverage($event->id)['status']);
        DB::table('purchases')->update(['expected_date' => now()->addDay()->toDateString()]);
        $coverage = app(EventCoverageService::class)->coverage($event->id);
        $this->assertSame('warning', $coverage['status']);
        $this->assertSame(10.0, $coverage['requirements'][0]['incoming_stock']);
    }

    public function test_substitution_is_audited_and_picked_allocations_cannot_be_replaced(): void
    {
        $old = $this->product();
        $new = $this->product('Galaxy');
        $this->stock($old);
        $this->stock($new);
        $event = $this->event();
        $allocation = $this->allocation($event, $old)['allocations'][0]->id;
        $result = $this->actingAs($this->admin, 'api')->postJson('/api/requirement-allocations/'.$allocation.'/replace', ['product_id' => $new->id, 'reason' => 'Preferencia artística'])->assertOk();
        $newId = $result->json('allocation.id');
        $this->assertDatabaseHas('requirement_allocations', ['id' => $allocation, 'status' => 'replaced']);
        $this->assertDatabaseCount('allocation_replacements', 1);
        app(EventPickingService::class)->create($event->id, $this->warehouse, $this->admin->id);
        $this->postJson('/api/requirement-allocations/'.$newId.'/replace', ['product_id' => $old->id, 'reason' => 'No permitido'])->assertUnprocessable();
    }

    public function test_legacy_purchase_uses_kardex_after_explicit_adoption(): void
    {
        $product = $this->product();
        $inventory = new Inventory;
        $inventory->forceFill(['id' => 1, 'name' => 'Anterior', 'location' => 'Bodega'])->save();
        $inventory->products()->attach($product->id, ['quantity' => 5, 'price' => 100]);
        DB::table('inventory_locations')->where('id', $this->warehouse)->update(['legacy_inventory_id' => 1]);
        app(InventoryMovementService::class)->adopt($this->warehouse, $this->admin->id);
        $purchase = new \App\Core\Data\Entities\Purchase(['user_id' => $this->admin->id, 'date' => now()->toDateString(), 'status' => 'confirmed', 'products' => collect([['product_id' => $product->id, 'quantity' => 2, 'price' => 100]]), 'equipments' => collect()]);
        $this->assertNotNull(app(\App\Core\Data\Services\PurchaseService::class)->create($purchase));
        $this->assertDatabaseHas('inventory_balances', ['location_id' => $this->warehouse, 'quantity' => 7]);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'purchase_receipt', 'quantity' => 2]);
    }

    public function test_migration_rollback_preserves_legacy_tables_and_packages(): void
    {
        $migration = require database_path('migrations/2026_10_07_000000_create_operational_inventory.php');
        $migration->down();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('purchase_items', 'quantiy'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('productables'));
        $this->assertDatabaseHas('packages', ['id' => $this->package->id, 'name' => 'Show']);
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('inventory_movements'));
    }
}
