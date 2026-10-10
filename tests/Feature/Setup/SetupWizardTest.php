<?php

namespace Tests\Feature\Setup;

use App\Models\Event;
use App\Models\Package;
use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('roles')->insert([['id' => 1, 'name' => 'admin', 'description' => 'Administrador'], ['id' => 2, 'name' => 'technician', 'description' => 'Técnico']]);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121000001']);
        $this->actingAs($this->admin);
    }

    private function save(int $step, string $action, array $data = [])
    {
        return $this->post('/settings/setup', ['step' => $step, 'action' => $action, ...$data]);
    }

    public function test_all_steps_render_with_examples_on_empty_installation_and_can_finish_without_inventory(): void
    {
        for ($step = 1; $step <= 7; $step++) {
            $this->get('/settings/setup?step='.$step)->assertOk()->assertSee('Ejemplo de llenado');
        }
        $this->get('/settings')->assertOk()->assertSee('Asistente de configuración inicial');
        $this->get('/home')->assertOk();
        $this->get('/panel/inventories')->assertOk();
        $this->get('/panel/purchases')->assertOk();
        $this->get('/panel/sales')->assertOk();
        $this->save(6, 'continue', ['inventory_mode' => 'empty'])->assertRedirect('/settings/setup?step=7');
        $this->get('/settings/setup')->assertOk()->assertViewHas('step', 7);
        $this->save(7, 'finish', ['inventory_mode' => 'empty'])->assertRedirect();
        $this->assertNotNull(DB::table('application_setup_progress')->value('completed_at'));
        foreach (['inventory_movements', 'inventory_balances', 'event_dispatches', 'purchases'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_wizard_builds_catalog_locations_and_real_opening_balances_without_purchase(): void
    {
        foreach (['categories' => 'Bombas aéreas', 'product_roles' => 'Producto pirotécnico', 'event_types' => 'Boda', 'client_types' => 'Particular', 'experience_levels' => 'Técnico autorizado'] as $catalog => $name) {
            $this->save(1, 'catalog', ['catalog' => $catalog, 'name' => $name, 'code' => 'BOMBAS'])->assertSessionHasNoErrors()->assertRedirect();
        }
        foreach (['polvorin', 'stand', 'raw_material'] as $area) {
            $this->save(2, 'location', ['name' => 'Lugar '.$area, 'code' => $area, 'inventory_area' => $area])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('inventory_locations', ['inventory_area' => $area, 'type' => $area === 'stand' ? 'stand' : 'warehouse']);
        }
        $this->save(3, 'product', ['name' => 'Bomba', 'caliber' => '3', 'sku' => 'BOM-3', 'unit' => 'pz', 'product_role_id' => DB::table('product_roles')->value('id'), 'category_id' => DB::table('categories')->value('id')])->assertSessionHasNoErrors()->assertRedirect();
        $product = DB::table('products')->value('id');
        $location = DB::table('inventory_locations')->where('inventory_area', 'polvorin')->value('id');
        $payload = ['product_id' => $product, 'location_id' => $location, 'quantity' => 120, 'notes' => 'Conteo inicial real'];
        $this->save(6, 'count', $payload)->assertRedirect();
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'location_id' => $location, 'quantity' => 120]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('purchases', 0);
        $this->save(6, 'count', $payload)->assertRedirect()->assertSessionHasErrors();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->save(7, 'finish', ['inventory_mode' => 'loaded'])->assertRedirect();
    }

    public function test_linking_and_assignment_are_repeatable_and_mobile_works_without_stock_or_dispatches(): void
    {
        $tech = User::factory()->create(['role_id' => 2, 'phone' => '3121000002']);
        $level = InventoryData::insert('experience_levels', ['name' => 'Técnico', 'description' => 'Autorizado']);
        $employee = InventoryData::insert('employees', ['name' => $tech->name, 'email' => $tech->email, 'phone' => '3121234567', 'salary' => '100', 'experience_level_id' => $level]);
        $package = Package::create(['name' => 'Boda 15 minutos', 'description' => 'Espectáculo', 'price' => 5000, 'duration' => '15 min']);
        $event = Event::create(['package_id' => $package->id, 'date' => '2026-10-10', 'phone' => '3121111111', 'client_name' => 'María y José', 'event_address' => 'Salón Las Palmas', 'event_date' => now()->addDay()]);
        for ($i = 0; $i < 2; $i++) {
            $this->save(4, 'employee', ['employee_id' => $employee, 'user_id' => $tech->id])->assertSessionHasNoErrors()->assertRedirect();
            $this->save(5, 'assignment', ['employee_id' => $employee, 'event_id' => $event->id])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('employee_event', 1);
        $this->assertDatabaseCount('inventory_permissions', 7);
        $this->actingAs($tech, 'api');
        $base = '/api/technician/events/'.$event->id;
        $this->getJson('/api/technician/events')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($base)->assertOk()->assertJsonPath('data.dispatch_ids', [])->assertJsonPath('data.permissions.receive_dispatch', false)->assertJsonPath('data.permissions.declare_return', false);
        $this->getJson($base.'/dispatch')->assertOk()->assertJsonPath('dispatches', [])->assertJsonPath('state', 'pending_dispatch');
        $this->getJson('/api/technician/sync/bootstrap')->assertOk();
        foreach (['timeline', 'incidents', 'return-declarations'] as $resource) {
            $this->getJson($base.'/'.$resource)->assertOk();
        }
        $protocol = $this->getJson($base.'/protocol')->assertOk()->json('data');
        foreach ($protocol['steps'] as $step) {
            $key = (string) Str::uuid();
            $this->postJson($base.'/steps/'.$step['id'].'/records', ['client_operation_id' => $key, 'protocol_version' => $protocol['version'], 'capture_mode' => 'manual', 'manual_reason' => 'Captura al finalizar la actividad', 'notes' => 'Actividad realizada sin despacho', 'occurred_at' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z'), 'recorded_at' => now()->utc()->format('Y-m-d\TH:i:s\Z')], ['Idempotency-Key' => $key])->assertSuccessful();
        }
        $key = (string) Str::uuid();
        $this->postJson($base.'/close', ['client_operation_id' => $key, 'protocol_version' => $protocol['version'], 'notes' => 'Evento concluido sin despachos', 'occurred_at' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z'), 'recorded_at' => now()->utc()->format('Y-m-d\TH:i:s\Z')], ['Idempotency-Key' => $key])->assertSuccessful();
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('event_dispatches', 0);
    }

    public function test_unlinked_mobile_account_gets_empty_agenda_and_sync(): void
    {
        $tech = User::factory()->create(['role_id' => 2, 'phone' => '3121000002']);
        $this->actingAs($tech, 'api');
        $this->getJson('/api/v2/dashboard')->assertOk();
        $this->getJson('/api/employee-events')->assertOk()->assertJsonPath('data.events', []);
        $this->getJson('/api/technician/events')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/technician/sync/bootstrap')->assertOk();
    }

    public function test_empty_inventory_allows_sale_draft_without_location_but_not_physical_sale(): void
    {
        $role = InventoryData::insert('product_roles', ['name' => 'Producto']);
        $product = app(\App\Services\Inventory\ProductCatalogService::class)->save(['name' => 'Bomba', 'unit' => 'pz', 'product_role_id' => $role], $this->admin->id);
        $payload = ['date' => '2026-10-10', 'client_name' => 'Cliente', 'status' => 'draft', 'items' => [['product_id' => $product['id'], 'quantity' => 2, 'unit_price' => 100]]];
        $sale = app(SaleService::class)->save($payload, $this->admin->id);
        $this->assertSame('draft', $sale['sale']['status']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SaleService::class)->save([...$payload, 'status' => 'completed'], $this->admin->id);
    }

    public function test_legacy_event_creation_no_longer_rolls_back_with_empty_inventory(): void
    {
        $package = Package::create(['name' => 'Boda', 'description' => 'Evento', 'price' => 5000, 'duration' => '15 min']);
        $event = app(\App\Core\UseCases\Events\CreateEvent::class)->execute(\App\Core\Data\Entities\Event::fromArray([
            'date' => '2026-10-10', 'phone' => '3121111111', 'client_name' => 'María y José', 'client_address' => 'Colima', 'event_address' => 'Salón Las Palmas', 'event_date' => now()->addDay()->toDateTimeString(),
            'packages' => [['id' => $package->id, 'name' => $package->name, 'price' => 5000, 'quantity' => 1]],
        ]));
        $this->assertDatabaseHas('events', ['id' => $event->id, 'client_name' => 'María y José']);
        $this->assertDatabaseCount('event_dispatches', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_setup_is_restricted_and_does_not_reassign_other_accounts(): void
    {
        $tech = User::factory()->create(['role_id' => 2, 'phone' => '3121000002']);
        $this->actingAs($tech)->get('/settings/setup')->assertForbidden();
        $this->save(7, 'finish', ['inventory_mode' => 'empty'])->assertForbidden();
        $this->actingAs($this->admin);
        $level = InventoryData::insert('experience_levels', ['name' => 'Técnico', 'description' => 'Autorizado']);
        $employee = InventoryData::insert('employees', ['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '3121234567', 'salary' => '100', 'experience_level_id' => $level, 'user_id' => $tech->id]);
        $this->save(4, 'employee', ['employee_id' => $employee, 'user_id' => $this->admin->id])->assertRedirect()->assertSessionHasErrors('employee_id');
        $this->assertDatabaseHas('employees', ['id' => $employee, 'user_id' => $tech->id]);
        $this->save(7, 'finish', ['inventory_mode' => 'loaded'])->assertRedirect()->assertSessionHasErrors('inventory_mode');
        $this->assertDatabaseCount('application_setup_progress', 0);
    }
}
