<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $root;

    private User $admin;

    private User $employee;

    private int $experience;

    private int $type;

    private int $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
        DB::table('roles')->insert([['id' => 1, 'name' => 'root', 'description' => 'Root'], ['id' => 2, 'name' => 'admin', 'description' => 'Admin'], ['id' => 3, 'name' => 'user', 'description' => 'Empleado']]);
        DB::table('product_roles')->insert([['id' => 1, 'name' => 'Producto'], ['id' => 2, 'name' => 'Material']]);
        $this->root = User::factory()->create(['role_id' => 1, 'phone' => '3121000001']);
        $this->admin = User::factory()->create(['role_id' => 2, 'phone' => '3121000002']);
        $this->employee = User::factory()->create(['role_id' => 3, 'phone' => '3121000003']);
        $this->experience = InventoryData::insert('experience_levels', ['name' => 'Nivel 1', 'description' => 'Operación']);
        $this->type = InventoryData::insert('event_types', ['name' => 'Boda', 'description' => 'Evento']);
        $this->product = app(ProductCatalogService::class)->save(['name' => 'Producto', 'product_role_id' => 1, 'unit' => 'pz'], $this->root->id)['id'];
        $this->actingAs($this->admin, 'api');
    }

    private function employeePayload(string $email = 'employee@example.com'): array
    {
        return ['name' => 'Empleado', 'email' => $email, 'phone' => '5551234567', 'role_id' => 3, 'experience_level_id' => $this->experience, 'status' => 'active', 'password' => 'password-123'];
    }

    private function packagePayload(): array
    {
        return ['name' => 'Paquete móvil', 'description' => 'Completo', 'price' => 2500, 'duration' => '25', 'event_type_id' => $this->type, 'experience_level_id' => $this->experience,
            'products' => [['product_id' => $this->product, 'quantity' => 12]], 'equipments' => []];
    }

    private function package(): int
    {
        return $this->postJson('/api/admin/packages', $this->packagePayload(), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('package.id');
    }

    private function event(int $package): int
    {
        return $this->postJson('/api/events', ['date' => '2026-10-19', 'client_name' => 'Cliente', 'phone' => '5550000000', 'event_address' => 'Plaza central', 'event_date' => '2026-11-01 21:30', 'event_type_id' => $this->type, 'package_id' => $package])->assertCreated()->json('event.id');
    }

    public function test_all_admin_endpoints_deny_employee_and_root_and_admin_use_actual_roles(): void
    {
        $this->getJson('/api/admin/product-roles')->assertOk()->assertJsonPath('data.0.id', 1);
        $this->actingAs($this->root, 'api')->getJson('/api/admin/product-roles')->assertOk();
        $this->actingAs($this->employee, 'api');
        foreach (['/api/employees', '/api/employee/1', '/api/equipments', '/api/equipment/1', '/api/client-types', '/api/client-type/1', '/api/admin/event-types', '/api/admin/experience-levels', '/api/admin/product-roles', '/api/events', '/api/inventory/dashboard', '/api/purchases', '/api/sales', '/api/products'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        foreach ([['POST', '/api/employee'], ['PUT', '/api/employee/1'], ['DELETE', '/api/employee/1'], ['POST', '/api/equipment'], ['POST', '/api/client-type'], ['POST', '/api/admin/media'], ['POST', '/api/admin/packages'], ['PUT', '/api/admin/packages/1'], ['PUT', '/api/admin/events/1'], ['DELETE', '/api/events/1'], ['POST', '/api/admin/events/1/confirm'], ['PUT', '/api/admin/events/1/employees']] as [$method, $path]) {
            $this->json($method, $path, [])->assertForbidden();
        }
    }

    public function test_root_and_admin_credentials_work_on_both_login_versions_and_inactive_accounts_are_rejected(): void
    {
        $this->mock(\Laravel\Passport\PersonalAccessTokenFactory::class, function ($mock) {
            $mock->shouldReceive('make')->times(4)->andReturn(new \Laravel\Passport\PersonalAccessTokenResult(['access_token' => 'fixture-token']));
        });
        foreach ([$this->root, $this->admin] as $user) {
            $user->forceFill(['password' => 'login-password', 'fcm_token' => 'private-device-token'])->save();
        }
        auth()->shouldUse('web');
        foreach (['/api/login', '/api/v2/login'] as $path) {
            foreach ([$this->root, $this->admin] as $user) {
                $this->postJson($path, ['email' => $user->email, 'password' => 'login-password'])->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.token', 'fixture-token')->assertJsonMissingPath('data.user.password')->assertJsonMissingPath('data.user.fcm_token');
            }
            $this->postJson($path, ['email' => $this->admin->email, 'password' => 'incorrect'])->assertUnauthorized();
        }
        User::whereKey($this->admin->id)->update(['status' => 'inactive']);
        foreach (['/api/login', '/api/v2/login'] as $path) {
            $this->postJson($path, ['email' => $this->admin->email, 'password' => 'login-password'])->assertForbidden();
        }
    }

    public function test_api_authentication_errors_are_json_without_accept_header(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get('/api/admin/product-roles')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_employee_transaction_role_protection_pagination_and_safe_archive(): void
    {
        $employee = $this->postJson('/api/employee', $this->employeePayload())->assertCreated()->assertJsonMissingPath('password')->json();
        $this->assertTrue(Hash::check('password-123', User::findOrFail($employee['user_id'])->password));
        $this->assertDatabaseHas('user_role', ['user_id' => $employee['user_id'], 'role_id' => 3]);
        $this->postJson('/api/employee', $this->employeePayload())->assertUnprocessable();
        $this->postJson('/api/employee', [...$this->employeePayload('promote@example.com'), 'role_id' => 2])->assertForbidden();
        $this->postJson('/api/employee', [...$this->employeePayload('root@example.com'), 'role_id' => 1])->assertUnprocessable();
        $this->putJson('/api/employee/'.$employee['id'], ['name' => 'Corregido'])->assertOk()->assertJsonPath('name', 'Corregido');
        for ($i = 0; $i < 21; $i++) {
            InventoryData::insert('employees', ['name' => 'Persona '.$i, 'email' => 'p'.$i.'@example.com', 'phone' => '5550000000', 'salary' => '0']);
        }
        $this->getJson('/api/employees?page=2')->assertOk()->assertJsonPath('current_page', 2)->assertJsonPath('total', 22)->assertJsonCount(2, 'data');
        $legacyId = DB::table('employees')->whereNull('user_id')->value('id');
        $userCount = User::count();
        $this->deleteJson('/api/employee/'.$legacyId)->assertOk()->assertJsonPath('status', 'inactive');
        $this->assertSame($userCount, User::count());
        $this->deleteJson('/api/employee/'.$employee['id'])->assertOk()->assertJsonPath('status', 'inactive')->assertJsonPath('archived', true);
        $this->assertDatabaseHas('users', ['id' => $employee['user_id'], 'status' => 'inactive']);
        $this->actingAs(User::findOrFail($employee['user_id']), 'api')->getJson('/api/inventory/dashboard')->assertForbidden();
        $this->actingAs($this->root, 'api')->postJson('/api/employee', [...$this->employeePayload('newadmin@example.com'), 'role_id' => 2, 'experience_level_id' => null])->assertCreated()->assertJsonPath('role_id', 2);
    }

    public function test_catalog_crud_uses_real_ids_and_soft_deletion(): void
    {
        foreach (['event-types', 'experience-levels'] as $catalog) {
            $row = $this->postJson('/api/admin/'.$catalog, ['name' => 'Nuevo '.$catalog, 'description' => null])->assertCreated()->json();
            $this->getJson('/api/admin/'.$catalog.'/'.$row['id'])->assertOk()->assertJsonPath('name', $row['name']);
            $this->putJson('/api/admin/'.$catalog.'/'.$row['id'], ['name' => 'Editado'])->assertOk();
            $this->deleteJson('/api/admin/'.$catalog.'/'.$row['id'])->assertOk()->assertJsonPath('active', false);
        }
        $type = $this->postJson('/api/client-type', ['name' => 'Preferente', 'discount' => 15])->assertCreated()->json('id');
        $this->putJson('/api/client-type/'.$type, ['discount' => 101])->assertUnprocessable();
        $this->getJson('/api/client-type/'.$type)->assertOk()->assertJsonPath('discount', fn ($value) => (float) $value === 15.0);
        $this->deleteJson('/api/client-type/'.$type)->assertOk()->assertJsonPath('active', false);
    }

    public function test_equipment_catalog_synchronizes_product_and_never_overwrites_kardex(): void
    {
        $row = $this->postJson('/api/equipment', ['name' => 'Consola', 'category' => 'detonators', 'quantity' => 2])->assertCreated()->json();
        $this->assertDatabaseHas('products', ['id' => $row['product_id'], 'legacy_equipment_id' => $row['id'], 'is_returnable' => true]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->putJson('/api/equipment/'.$row['id'], ['name' => 'Consola editada', 'quantity' => 2])->assertOk();
        $this->putJson('/api/equipment/'.$row['id'], ['quantity' => 3])->assertConflict()->assertJsonPath('code', 'EQUIPMENT_STOCK_IMMUTABLE');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->getJson('/api/equipment/'.$row['id'])->assertOk()->assertJsonPath('name', 'Consola editada');
        $this->deleteJson('/api/equipment/'.$row['id'])->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('products', ['id' => $row['product_id'], 'active' => false]);
    }

    public function test_atomic_package_save_retries_and_bad_equipment_roll_back_and_snapshots_survive_edits(): void
    {
        $equipment = $this->postJson('/api/equipment', ['name' => 'Mortero', 'category' => 'mortars', 'quantity' => 0])->assertCreated()->json('id');
        $data = [...$this->packagePayload(), 'equipments' => [['equipment_id' => $equipment, 'quantity' => 1, 'required' => false]]];
        $key = (string) Str::uuid();
        $package = $this->postJson('/api/admin/packages', $data, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('package.equipments.0.required', false)->json('package.id');
        $this->postJson('/api/admin/packages', $data, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('package.id', $package);
        $this->postJson('/api/admin/packages', [...$data, 'name' => 'Cambio'], ['Idempotency-Key' => $key])->assertConflict();
        $unmapped = InventoryData::insert('equipments', ['name' => 'Sin adaptador', 'description' => '', 'unit' => 'pz']);
        $this->postJson('/api/admin/packages', [...$data, 'equipments' => [['equipment_id' => $unmapped, 'quantity' => 1, 'required' => true]]])->assertUnprocessable();
        $this->assertDatabaseCount('packages', 1);
        $event = $this->event($package);
        $before = DB::table('event_requirements')->where('event_id', $event)->get()->toJson();
        $this->assertSame(1, DB::table('event_requirements')->where('event_id', $event)->count());
        $data['products'][0]['quantity'] = 20;
        $this->putJson('/api/admin/packages/'.$package, $data)->assertOk()->assertJsonPath('package.products.0.quantity', 20)->assertJsonPath('package.duration', '25');
        $this->assertSame($before, DB::table('event_requirements')->where('event_id', $event)->get()->toJson());
        $this->getJson('/api/packages/'.$package)->assertOk()->assertJsonPath('package.products.0.quantity', 20);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_event_edit_assign_transitions_versioning_and_logical_delete_preserve_history(): void
    {
        $package = $this->package();
        $event = $this->event($package);
        $staff = $this->postJson('/api/employee', $this->employeePayload())->assertCreated()->json();
        $this->putJson('/api/admin/events/'.$event.'/employees', ['employee_ids' => [$staff['id']]])->assertOk()->assertJsonPath('employees.0.id', $staff['id']);
        $this->deleteJson('/api/employee/'.$staff['id'])->assertConflict()->assertJsonPath('code', 'EMPLOYEE_ASSIGNED');
        $this->actingAs(User::findOrFail($staff['user_id']), 'api')->getJson('/api/technician/events/'.$event)->assertOk();
        $this->actingAs($this->admin, 'api');
        $this->putJson('/api/admin/events/'.$event, ['date' => '2020-01-01', 'client_name' => 'Corregido', 'package_quantity' => 2])->assertOk()->assertJsonPath('client_name', 'Corregido')->assertJsonPath('date', '2026-10-19');
        $this->assertDatabaseHas('event_requirements', ['event_id' => $event, 'quantity_required' => 24]);
        $this->assertDatabaseHas('mobile_event_protocols', ['event_id' => $event, 'version' => 3]);
        $key = (string) Str::uuid();
        $this->postJson('/api/admin/events/'.$event.'/confirm', ['reason' => 'Contrato confirmado'], ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('status', 'confirmed');
        $this->postJson('/api/admin/events/'.$event.'/confirm', ['reason' => 'Contrato confirmado'], ['Idempotency-Key' => $key])->assertOk();
        $this->postJson('/api/admin/events/'.$event.'/close', ['reason' => 'Cierre'])->assertConflict()->assertJsonPath('code', 'EVENT_CLOSURE_BLOCKED');
        $this->getJson('/api/events')->assertOk()->assertJsonPath('data.events.0.status', 'confirmed')->assertJsonPath('data.events.0.package_id', $package);
        $this->putJson('/api/admin/events/'.$event.'/employees', ['employee_ids' => []])->assertOk();
        $this->actingAs(User::findOrFail($staff['user_id']), 'api')->getJson('/api/technician/events/'.$event)->assertForbidden();
        $this->actingAs($this->admin, 'api')->deleteJson('/api/events/'.$event)->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('events', ['id' => $event]);
        $this->postJson('/api/admin/events/'.$event.'/reopen', ['reason' => 'Reprogramación autorizada'])->assertOk()->assertJsonPath('status', 'confirmed');
        $this->assertDatabaseHas('mobile_event_protocols', ['event_id' => $event, 'version' => 4]);
    }

    public function test_technical_product_attributes_and_image_can_be_cleared_without_stock_changes(): void
    {
        $definition = InventoryData::insert('attribute_definitions', ['name' => 'Disparos', 'code' => 'shots', 'data_type' => 'number']);
        DB::table('product_attribute_values')->insert(['product_id' => $this->product, 'attribute_definition_id' => $definition, 'value_number' => 6, 'created_at' => now(), 'updated_at' => now()]);
        $this->putJson('/api/products/'.$this->product, ['image_url' => 'https://example.com/product.png', 'attributes' => ['shape' => 'Recta', 'duration' => 30, 'shots' => 25]])->assertOk()->assertJsonPath('attributes.shots', '25')->assertJsonPath('image_url', 'https://example.com/product.png');
        $this->assertSame('Recta', app(\App\Services\Inventory\ProductMatcherService::class)->attribute(Product::findOrFail($this->product), 'shape'));
        $this->putJson('/api/products/'.$this->product, ['image_url' => null, 'attributes' => ['shape' => null, 'duration' => null, 'shots' => null]])->assertOk()->assertJsonPath('attributes.shots', null)->assertJsonPath('image_url', null);
        $this->putJson('/api/products/'.$this->product, ['attributes' => ['shots' => -1]])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_dashboard_counts_all_events_and_active_staff_with_a_bounded_number_of_queries(): void
    {
        $package = Package::create(['name' => 'Vacío', 'description' => '', 'price' => 0, 'duration' => '1', 'status' => 'active']);
        $events = [];
        for ($i = 0; $i < 61; $i++) {
            $events[] = Event::create(['package_id' => $package->id, 'date' => '2026-10-19', 'client_name' => 'Evento '.$i, 'phone' => '5550000000', 'event_address' => 'Plaza', 'event_date' => '2026-10-20 20:00']);
        }
        foreach (['cancelled', 'closed'] as $status) {
            $event = Event::create(['package_id' => $package->id, 'date' => '2026-10-19', 'client_name' => 'Excluido', 'phone' => '5550000000', 'event_address' => 'Plaza', 'event_date' => '2026-10-20 20:00']);
            $event->forceFill(['status' => $status])->save();
        }
        foreach (['active', 'vacation', 'inactive'] as $status) {
            InventoryData::insert('employees', ['name' => $status, 'email' => $status.'@example.com', 'phone' => '5550000000', 'salary' => '0', 'status' => $status]);
        }
        InventoryData::insert('employees', ['name' => 'Admin', 'email' => 'staffadmin@example.com', 'phone' => '5550000000', 'salary' => '0', 'user_id' => $this->admin->id]);
        $warehouse = InventoryData::insert('inventory_locations', ['name' => 'Principal', 'code' => 'MAIN', 'type' => 'warehouse']);
        app(InventoryMovementService::class)->record($this->product, 10, null, $warehouse, 'adjustment_in', $this->admin->id, 'Apertura');
        foreach (array_slice($events, 0, 2) as $event) {
            InventoryData::insert('event_requirements', ['event_id' => $event->id, 'requirement_type' => 'specific_product', 'product_id' => $this->product, 'quantity_required' => 8, 'snapshot' => [], 'metadata' => [], 'allow_substitution' => false]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/inventory/dashboard')->assertOk()->assertJsonPath('upcoming_events', 61)->assertJsonPath('active_employees', 1)->assertJsonPath('deficit_events', 1)->assertJsonPath('low_stock_products', 0);
        $firstCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 80; $i++) {
            Product::create(['name' => 'Producto '.$i, 'description' => '', 'unit' => 'pz', 'product_role_id' => 1]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/inventory/dashboard')->assertOk()->assertJsonPath('upcoming_events', 61)->assertJsonPath('low_stock_products', 80)->assertJsonPath('deficit_events', 1);
        $this->assertLessThanOrEqual($firstCount + 2, count(DB::getQueryLog()));
        $this->assertLessThan(30, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->getJson('/api/events?page=2')->assertOk()->assertJsonPath('data.current_page', 2)->assertJsonPath('data.total', 63)->assertJsonCount(10, 'data.events');
    }

    public function test_event_contract_edits_cannot_invalidate_allocations_and_cancel_releases_only_reservations(): void
    {
        $package = $this->package();
        $event = $this->event($package);
        $warehouse = InventoryData::insert('inventory_locations', ['name' => 'Principal', 'code' => 'MAIN', 'type' => 'warehouse']);
        app(InventoryMovementService::class)->record($this->product, 20, null, $warehouse, 'adjustment_in', $this->admin->id, 'Apertura');
        $requirement = DB::table('event_requirements')->where('event_id', $event)->value('id');
        app(\App\Services\Inventory\RequirementAllocationService::class)->allocate($event, $requirement, ['warehouse_location_id' => $warehouse, 'allocations' => [['product_id' => $this->product, 'quantity' => 12]]], $this->admin);
        $this->putJson('/api/admin/events/'.$event, ['package_quantity' => 2, 'event_address' => 'Cambio'])->assertConflict()->assertJsonPath('code', 'EVENT_OPERATIONAL_CONFLICT');
        $this->assertDatabaseHas('events', ['id' => $event, 'event_address' => 'Plaza central']);
        $this->postJson('/api/inventory/adjustments', ['product_id' => $this->product, 'location_id' => $warehouse, 'quantity' => 9, 'direction' => 'out', 'notes' => 'Salida manual'])->assertUnprocessable()->assertJsonPath('code', 'INVENTORY_STOCK_INSUFFICIENT');
        $this->deleteJson('/api/events/'.$event)->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->postJson('/api/events/'.$event.'/requirements/'.$requirement.'/allocations', ['warehouse_location_id' => $warehouse, 'allocations' => [['product_id' => $this->product, 'quantity' => 12]]])->assertConflict()->assertJsonPath('code', 'EVENT_STATE_CONFLICT');
        $this->assertDatabaseHas('requirement_allocations', ['event_requirement_id' => $requirement, 'status' => 'cancelled']);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product, 'location_id' => $warehouse, 'quantity' => 20]);
        $this->postJson('/api/admin/events/'.$event.'/reopen', ['reason' => 'Retomar preparación'])->assertOk()->assertJsonPath('logistics_status', 'pending');
        $this->postJson('/api/events/'.$event.'/requirements/'.$requirement.'/allocations', ['warehouse_location_id' => $warehouse, 'allocations' => [['product_id' => $this->product, 'quantity' => 12]]])->assertOk();
    }

    public function test_assignments_reject_inactive_or_busy_staff_and_reopening_keeps_protocol_history(): void
    {
        $package = $this->package();
        $first = $this->event($package);
        $second = $this->event($package);
        $employee = $this->postJson('/api/employee', $this->employeePayload())->assertCreated()->json('id');
        $this->putJson('/api/admin/events/'.$first.'/employees', ['employee_ids' => [$employee]])->assertOk();
        $this->putJson('/api/admin/events/'.$second.'/employees', ['employee_ids' => [$employee]])->assertConflict()->assertJsonPath('code', 'EMPLOYEE_UNAVAILABLE');
        $this->putJson('/api/admin/events/'.$first.'/employees', ['employee_ids' => []])->assertOk();
        $this->putJson('/api/employee/'.$employee, ['status' => 'vacation'])->assertOk();
        $this->putJson('/api/admin/events/'.$second.'/employees', ['employee_ids' => [$employee]])->assertUnprocessable();
        Event::whereKey($first)->update(['status' => 'confirmed']);
        DB::table('mobile_event_protocols')->where('event_id', $first)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $this->admin->id]);
        DB::table('event_requirements')->where('event_id', $first)->update(['status' => 'closed']);
        $before = DB::table('mobile_protocol_steps')->whereIn('protocol_id', DB::table('mobile_event_protocols')->where('event_id', $first)->select('id'))->orderBy('id')->get()->toJson();
        $this->postJson('/api/admin/events/'.$first.'/close', ['reason' => 'Conciliación terminada'])->assertOk()->assertJsonPath('status', 'closed');
        $this->postJson('/api/admin/events/'.$first.'/reopen', ['reason' => 'Corrección comercial'])->assertOk()->assertJsonPath('status', 'confirmed');
        $this->assertDatabaseHas('mobile_event_protocols', ['event_id' => $first, 'version' => 3, 'status' => 'closed']);
        $this->assertSame($before, DB::table('mobile_protocol_steps')->whereIn('protocol_id', DB::table('mobile_event_protocols')->where('event_id', $first)->select('id'))->orderBy('id')->get()->toJson());
    }

    public function test_advanced_package_rules_and_legacy_duration_are_preserved(): void
    {
        $id = $this->package();
        Package::whereKey($id)->update(['duration' => '30 segundos']);
        $data = [...$this->packagePayload(), 'duration' => '30 segundos'];
        $this->putJson('/api/admin/packages/'.$id, $data)->assertOk()->assertJsonPath('package.duration', '30 segundos');
        $category = InventoryData::insert('categories', ['name' => 'Categoría', 'code' => 'CAT']);
        app(\App\Services\Inventory\PackageRequirementService::class)->create($id, ['requirement_type' => 'specification', 'category_id' => $category, 'quantity' => 1], $this->admin->id);
        $this->putJson('/api/admin/packages/'.$id, [...$data, 'name' => 'No debe guardarse'])->assertConflict()->assertJsonPath('code', 'ADVANCED_PACKAGE_REQUIREMENTS');
        $this->assertDatabaseHas('packages', ['id' => $id, 'name' => 'Paquete móvil', 'duration' => '30 segundos']);
    }

    public function test_uploaded_media_are_signed_validated_retrievable_and_only_orphans_are_pruned(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j33sAAAAASUVORK5CYII=');
        $upload = fn () => $this->post('/api/admin/media', ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('photo.png', $png)], ['Accept' => 'application/json'])->assertCreated()->json();
        $first = $upload();
        $this->get($first['url'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/admin/media/'.$first['id'])->assertForbidden();
        $this->putJson('/api/products/'.$this->product, ['image_url' => $first['url']])->assertOk();
        $upload();
        $this->post('/api/admin/media', ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/admin/media', ['kind' => 'video', 'file' => UploadedFile::fake()->create('big.mp4', 51201)], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->travel(2)->days();
        $this->get($first['url'])->assertForbidden();
        $this->artisan('admin:prune-media')->assertSuccessful();
        $this->assertDatabaseCount('admin_media', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $url = $this->getJson('/api/products/'.$this->product)->assertOk()->json('image_url');
        $this->get($url)->assertOk();
        $this->putJson('/api/products/'.$this->product, ['image_url' => url('/admin/media/'.Str::uuid())])->assertUnprocessable();
        $this->putJson('/api/products/'.$this->product, ['image_url' => 'file:///tmp/photo.png'])->assertUnprocessable();
    }
}
