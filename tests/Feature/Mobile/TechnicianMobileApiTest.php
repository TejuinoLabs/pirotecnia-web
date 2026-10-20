<?php

namespace Tests\Feature\Mobile;

use App\Models\Event;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\EventDispatchService;
use App\Services\Inventory\EventPickingService;
use App\Services\Inventory\EventPreparationService;
use App\Services\Inventory\EventRequirementService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\ProductCatalogService;
use App\Services\Inventory\RequirementAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TechnicianMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tech;

    private User $other;

    private Event $event;

    private Package $package;

    private Product $product;

    private int $warehouse;

    private int $dispatch;

    private int $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(18, 0, 0));
        DB::table('roles')->insert([['id' => 1, 'name' => 'admin', 'description' => 'Admin'], ['id' => 2, 'name' => 'technician', 'description' => 'Técnico'], ['id' => 3, 'name' => 'supervisor', 'description' => 'Supervisor'], ['id' => 4, 'name' => 'user', 'description' => 'Usuario']]);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->tech = User::factory()->create(['role_id' => 2, 'phone' => '3121234568']);
        $this->other = User::factory()->create(['role_id' => 2, 'phone' => '3121234569']);
        $this->package = Package::create(['name' => 'Paquete firmado', 'description' => 'Descripción original', 'price' => 5000, 'duration' => '15 min']);
        $this->event = $this->makeEvent();
        $this->assign($this->tech, $this->event);
        $this->warehouse = InventoryData::insert('inventory_locations', ['name' => 'Almacén', 'code' => 'MAIN', 'type' => 'warehouse']);
        $this->product = Product::findOrFail(app(ProductCatalogService::class)->save(['name' => 'Bomba', 'caliber' => '3', 'product_role_id' => 1, 'unit' => 'pz', 'sku' => 'BOM-3'], $this->admin->id)['id']);
        $this->event->products()->attach($this->product->id, ['quantity' => 3, 'price' => 100]);
        app(InventoryMovementService::class)->record($this->product->id, 10, null, $this->warehouse, 'adjustment_in', $this->admin->id, 'Apertura');
        $this->prepareDispatch();
    }

    private function prepareDispatch(bool $confirm = true): void
    {
        app(EventRequirementService::class)->snapshot($this->event, $this->admin->id);
        $requirement = DB::table('event_requirements')->where('event_id', $this->event->id)->value('id');
        app(RequirementAllocationService::class)->allocate($this->event->id, $requirement, ['warehouse_location_id' => $this->warehouse, 'allocations' => [['product_id' => $this->product->id, 'quantity' => 3]]], $this->admin);
        $picking = app(EventPickingService::class)->create($this->event->id, $this->warehouse, $this->admin->id);
        foreach ($picking['items'] as $item) {
            app(EventPickingService::class)->confirm($this->event->id, $picking['picking']['id'], $item->id, (float) $item->quantity_required, $this->admin->id);
        }
        app(EventPickingService::class)->complete($this->event->id, $picking['picking']['id'], $this->admin->id);
        app(EventPreparationService::class)->transition($this->event->id, true, $this->admin->id);
        $dispatch = app(EventDispatchService::class)->create($this->event->id, ['picking_id' => $picking['picking']['id'], 'technician_id' => $this->tech->id], $this->admin->id);
        $this->dispatch = $dispatch['dispatch']['id'];
        $this->line = $dispatch['items'][0]->id;
        if ($confirm) {
            app(EventDispatchService::class)->confirm($this->dispatch, $this->admin->id);
        }
    }

    private function makeEvent(): Event
    {
        $event = Event::create(['package_id' => $this->package->id, 'date' => '2026-10-10', 'phone' => '3120000000', 'client_name' => 'Contacto autorizado', 'event_address' => 'Salón', 'event_date' => now()->addDay()]);
        $event->packages()->attach($this->package->id, ['quantity' => 1, 'price' => 5000]);

        return $event;
    }

    private function assign(User $actor, Event $event): void
    {
        $level = DB::table('experience_levels')->value('id') ?? InventoryData::insert('experience_levels', ['name' => 'Técnico', 'description' => 'Técnico']);
        $employee = DB::table('employees')->where('user_id', $actor->id)->value('id') ?? InventoryData::insert('employees', ['name' => $actor->name, 'email' => $actor->email, 'phone' => $actor->phone, 'salary' => '100', 'experience_level_id' => $level, 'user_id' => $actor->id]);
        DB::table('employee_event')->insert(['event_id' => $event->id, 'employee_id' => $employee, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function times(): array
    {
        return ['occurred_at' => now()->subMinutes(2)->utc()->format('Y-m-d\TH:i:s\Z'), 'recorded_at' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z')];
    }

    private function url(string $suffix = ''): string
    {
        return '/api/technician/events/'.$this->event->id.$suffix;
    }

    private function write(string $method, string $url, array $payload, ?string $key = null)
    {
        $key ??= (string) Str::uuid();

        return $this->json($method, $url, ['client_operation_id' => $key, ...$payload], ['Idempotency-Key' => $key]);
    }

    private function protocol(): array
    {
        return $this->getJson($this->url('/protocol'))->assertOk()->json('data');
    }

    private function recordPayload(int $version): array
    {
        return [...$this->times(), 'protocol_version' => $version, 'capture_mode' => 'manual', 'manual_reason' => 'Captura posterior sin conexión', 'notes' => 'Hito administrativo', 'attachment_ids' => []];
    }

    private function receive(string $quantity = '3.000', string $status = 'received')
    {
        return $this->postJson('/api/technician/dispatches/'.$this->dispatch.'/receive', ['expected_version' => DB::table('event_dispatches')->where('id', $this->dispatch)->value('version'), 'items' => [['dispatch_item_id' => $this->line, 'received_quantity' => $quantity, 'status' => $status, 'notes' => 'Recepción revisada']]], ['Idempotency-Key' => (string) Str::uuid()]);
    }

    public function test_routes_require_login_and_assignment_including_legacy_detail(): void
    {
        $this->getJson('/api/technician/events')->assertUnauthorized();
        $this->actingAs($this->other, 'api');
        $this->getJson('/api/technician/events')->assertOk()->assertJsonPath('meta.total', 0);
        foreach (['', '/protocol', '/dispatch', '/timeline', '/incidents', '/return-declarations', '/departure-pass'] as $suffix) {
            $this->getJson($this->url($suffix))->assertForbidden();
        }
        $this->getJson('/api/event/'.$this->event->id)->assertForbidden();
        $this->getJson('/api/employee-events')->assertOk()->assertJsonPath('data.total', 0);
        $this->write('POST', $this->url('/incidents'), [...$this->times(), 'category' => 'other', 'severity' => 'low', 'description' => 'Ajeno'])->assertForbidden();
        $this->receive()->assertForbidden();
        $this->assertDatabaseCount('mobile_incidents', 0);
        $this->actingAs($this->admin, 'api')->getJson('/api/event/99999')->assertNotFound();
    }

    public function test_event_snapshots_are_versioned_private_filtered_and_immutable_after_template_edits(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->getJson('/api/technician/events?status=upcoming&search=Salón')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.assignment.user_id', $this->tech->id);
        $original = $this->getJson($this->url())->assertOk()->assertJsonPath('data.package_snapshot.name', 'Paquete firmado')->json('data');
        $this->package->update(['name' => 'Nuevo nombre']);
        config(['mobile.protocol.version' => 99, 'mobile.protocol.steps' => []]);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.package_snapshot.name', 'Paquete firmado')->assertJsonPath('data.protocol.version', 2);
        $this->getJson($this->url('/protocol'))->assertOk()->assertJsonCount(5, 'data.steps');
        $this->assertSame('3.000', $original['requirements'][0]['quantity_required']);
        $this->getJson('/api/technician/events?from=invalid')->assertUnprocessable();
        $this->getJson('/api/technician/events?from=2026-10-12T00:00:00Z&to=2026-10-11T00:00:00Z')->assertUnprocessable();
        $this->getJson('/api/technician/events?to=2026-10-12T00:00:00Z')->assertOk();
    }

    public function test_dispatch_enrichment_receipt_and_atomic_discrepancy_incidents_are_readable(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->getJson($this->url('/dispatch'))->assertOk()->assertJsonPath('dispatches.0.items.0.name', 'Bomba 3"')->assertJsonPath('dispatches.0.items.0.quantity', '3.000')->assertJsonPath('dispatches.0.items.0.quantity_precision', 0)->assertJsonPath('dispatches.0.items.0.category', 'pyrotechnic');
        $this->receive('1.500', 'quantity_mismatch')->assertUnprocessable();
        $this->assertDatabaseCount('technician_receipts', 0);
        $response = $this->receive('2.000', 'quantity_mismatch')->assertOk()->assertJsonCount(1, 'incident_ids');
        $this->getJson($this->url('/dispatch'))->assertOk()->assertJsonPath('dispatches.0.receipt.items.0.received_quantity', '2.000')->assertJsonPath('dispatches.0.receipt.status', 'quantity_mismatch')->assertJsonCount(1, 'dispatches.0.incident_ids');
        $this->getJson($this->url('/incidents'))->assertOk()->assertJsonPath('data.0.category', 'quantity_mismatch');
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product->id, 'location_id' => $this->warehouse, 'quantity' => 7]);
        DB::table('employee_event')->where('event_id', $this->event->id)->delete();
        $this->getJson($this->url('/dispatch'))->assertForbidden();
    }

    public function test_step_writes_require_matching_uuid_manual_reason_dependencies_and_current_version(): void
    {
        $this->actingAs($this->tech, 'api');
        $p = $this->protocol();
        $step = $p['steps'][0]['id'];
        $payload = $this->recordPayload($p['version']);
        $this->postJson($this->url('/steps/'.$step.'/records'), $payload)->assertUnprocessable();
        $this->postJson($this->url('/steps/'.$step.'/records'), ['client_operation_id' => (string) Str::uuid(), ...$payload], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->write('POST', $this->url('/steps/'.$step.'/records'), array_replace($payload, ['manual_reason' => null]))->assertUnprocessable();
        $this->write('POST', $this->url('/steps/'.$step.'/records'), array_replace($payload, ['protocol_version' => 999]))->assertConflict()->assertJsonPath('code', 'PROTOCOL_VERSION_CONFLICT');
        $this->write('POST', $this->url('/steps/'.$p['steps'][1]['id'].'/records'), $payload)->assertConflict()->assertJsonPath('code', 'STEP_DEPENDENCY_CONFLICT');
        $key = (string) Str::uuid();
        $first = $this->write('POST', $this->url('/steps/'.$step.'/records'), $payload, $key)->assertCreated()->json();
        $this->write('POST', $this->url('/steps/'.$step.'/records'), array_reverse($payload, true), $key)->assertCreated()->assertExactJson($first);
        $this->write('POST', $this->url('/steps/'.$step.'/records'), array_replace($payload, ['notes' => 'Cambió']), $key)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_CONFLICT');
        $this->write('POST', $this->url('/steps/'.$step.'/records'), $payload)->assertConflict()->assertJsonPath('code', 'STEP_ALREADY_RECORDED');
        $this->assertDatabaseCount('mobile_step_records', 1);
        $this->getJson($this->url('/protocol'))->assertOk()->assertJsonPath('data.steps.0.records.0.id', $first['data']['id']);
    }

    public function test_corrections_preserve_original_and_check_author_revision_and_audit_timeline(): void
    {
        $this->actingAs($this->tech, 'api');
        $p = $this->protocol();
        $original = $this->write('POST', $this->url('/steps/'.$p['steps'][0]['id'].'/records'), $this->recordPayload($p['version']))->assertCreated()->json('data');
        $key = (string) Str::uuid();
        $url = '/api/technician/step-records/'.$original['id'];
        $payload = ['expected_revision' => 1, 'occurred_at' => now()->subMinutes(3)->utc()->format('Y-m-d\TH:i:s\Z'), 'notes' => 'Corrección', 'correction_reason' => 'Hora revisada'];
        $this->write('PATCH', $url, $payload, $key)->assertOk()->assertJsonPath('data.revision', 2);
        $this->write('PATCH', $url, $payload, $key)->assertOk()->assertJsonPath('data.revision', 2);
        $this->write('PATCH', $url, $payload)->assertConflict()->assertJsonPath('code', 'RECORD_REVISION_CONFLICT');
        $this->assertDatabaseCount('mobile_record_corrections', 1);
        $saved = json_decode(DB::table('mobile_step_records')->value('original_payload'), true);
        $this->assertSame($original['occurred_at'], $saved['occurred_at']);
        $timeline = $this->getJson($this->url('/timeline'))->assertOk()->json('data');
        $this->assertCount(1, array_filter($timeline, fn ($entry) => $entry['entity_type'] === 'correction'));
        $this->assertContains('dispatch', array_column($timeline, 'entity_type'));
        $this->assign($this->other, $this->event);
        $this->actingAs($this->other, 'api');
        $this->write('PATCH', $url, array_replace($payload, ['expected_revision' => 2]))->assertForbidden();
    }

    public function test_incidents_validate_related_entities_and_idempotency_is_global_across_actions(): void
    {
        $this->actingAs($this->tech, 'api');
        $data = [...$this->times(), 'category' => 'delay', 'severity' => 'low', 'description' => 'Demora administrativa', 'dispatch_item_id' => $this->line, 'attachment_ids' => []];
        $this->write('POST', $this->url('/incidents'), array_replace($data, ['dispatch_item_id' => 99999]))->assertForbidden();
        $key = (string) Str::uuid();
        $first = $this->write('POST', $this->url('/incidents'), $data, $key)->assertCreated()->json();
        $this->write('POST', $this->url('/incidents'), $data, $key)->assertCreated()->assertExactJson($first);
        $p = $this->protocol();
        $this->write('POST', $this->url('/steps/'.$p['steps'][0]['id'].'/records'), $this->recordPayload($p['version']), $key)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_CONFLICT');
        $this->getJson($this->url('/incidents?status=open'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertDatabaseCount('mobile_incidents', 1);
    }

    public function test_return_declarations_reconcile_received_stock_revision_and_never_move_inventory(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->receive('2.000', 'quantity_mismatch')->assertOk();
        $line = ['dispatch_item_id' => $this->line, 'quantity_consumed' => '0.000', 'quantity_returned' => '1.000', 'quantity_damaged' => '0.000', 'quantity_lost' => '0.000'];
        $data = [...$this->times(), 'dispatch_id' => $this->dispatch, 'expected_revision' => 1, 'items' => [$line]];
        $movements = DB::table('inventory_movements')->count();
        $key = (string) Str::uuid();
        $this->write('POST', $this->url('/return-declarations'), $data, $key)->assertCreated()->assertJsonPath('data.revision', 2)->assertJsonPath('data.items.0.quantity_pending', '1.000');
        $this->write('POST', $this->url('/return-declarations'), $data, $key)->assertCreated();
        $this->write('POST', $this->url('/return-declarations'), $data)->assertConflict()->assertJsonPath('code', 'RETURN_REVISION_CONFLICT');
        $this->write('POST', $this->url('/return-declarations'), array_replace($data, ['expected_revision' => 2, 'items' => [array_replace($line, ['quantity_returned' => '2.000'])]]))->assertConflict()->assertJsonPath('code', 'RETURN_QUANTITY_CONFLICT');
        $this->write('POST', $this->url('/return-declarations'), array_replace($data, ['expected_revision' => 2, 'items' => [array_replace($line, ['quantity_returned' => '0.1234'])]]))->assertUnprocessable();
        $this->write('POST', $this->url('/return-declarations'), array_replace($data, ['expected_revision' => 2]))->assertCreated()->assertJsonPath('data.revision', 3);
        $this->getJson($this->url('/return-declarations'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('reconciliations.0.items.0.quantity_pending', '0.000');
        $this->assertSame($movements, DB::table('inventory_movements')->count());
        $this->actingAs($this->admin, 'api');
        $warehouse = $this->postJson('/api/events/'.$this->event->id.'/returns', ['event_dispatch_id' => $this->dispatch, 'warehouse_location_id' => $this->warehouse, 'items' => [array_replace($line, ['quantity_returned' => '2.000', 'quantity_lost' => '1.000'])]])->assertOk()->json('return.id');
        $this->postJson('/api/event-returns/'.$warehouse.'/confirm')->assertOk();
        $this->actingAs($this->tech, 'api');
        $this->getJson($this->url('/return-declarations'))->assertOk()->assertJsonPath('data.0.status', 'warehouse_confirmed')->assertJsonPath('data.0.warehouse_return_id', $warehouse);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product->id, 'location_id' => $this->warehouse, 'quantity' => 9]);
    }

    public function test_private_attachment_upload_retry_link_download_expiry_and_type_restrictions(): void
    {
        Storage::fake('local');
        $this->actingAs($this->tech, 'api');
        $key = (string) Str::uuid();
        $file = UploadedFile::fake()->create('evidencia.pdf', 20, 'application/pdf');
        $payload = ['client_operation_id' => $key, 'entity_type' => 'step_record', 'file' => $file];
        $id = $this->post($this->url('/attachments'), $payload, ['Idempotency-Key' => $key, 'Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->post($this->url('/attachments'), $payload, ['Idempotency-Key' => $key, 'Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('mobile_attachments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->getJson('/api/technician/attachments/'.$id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $p = $this->protocol();
        $this->write('POST', $this->url('/steps/'.$p['steps'][0]['id'].'/records'), array_replace($this->recordPayload($p['version']), ['attachment_ids' => [$id]]))->assertCreated();
        $this->assertDatabaseHas('mobile_attachments', ['id' => $id, 'entity_id' => 1]);
        $this->actingAs($this->other, 'api')->getJson('/api/technician/attachments/'.$id)->assertForbidden();
        $this->actingAs($this->tech, 'api');
        $bad = (string) Str::uuid();
        $this->post($this->url('/attachments'), ['client_operation_id' => $bad, 'entity_type' => 'incident', 'file' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')], ['Idempotency-Key' => $bad, 'Accept' => 'application/json'])->assertUnprocessable();
        $this->travel(31)->days();
        $this->getJson('/api/technician/attachments/'.$id)->assertNotFound();
        $this->getJson('/api/technician/sync/bootstrap')->assertOk()->assertJsonPath('data.deleted_records.0.entity_id', $id);
        $this->artisan('mobile:prune-attachments')->assertSuccessful();
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_bootstrap_cursor_watermark_revocations_and_new_assignments_ignore_client_clock(): void
    {
        $this->actingAs($this->tech, 'api');
        config(['mobile.page_size' => 1]);
        $second = $this->makeEvent();
        $this->assign($this->tech, $second);
        $first = $this->getJson('/api/technician/sync/bootstrap')->assertOk()->assertJsonCount(1, 'data.events')->json();
        $this->assertNotNull($first['meta']['next_cursor']);
        $cursor = urlencode($first['meta']['next_cursor']);
        $this->getJson('/api/technician/sync/bootstrap?cursor='.$cursor)->assertOk()->assertJsonPath('meta.server_time', $first['meta']['server_time'])->assertJsonPath('meta.next_cursor', null)->assertJsonPath('data.events.0.id', $second->id);
        $this->actingAs($this->other, 'api')->getJson('/api/technician/sync/bootstrap?cursor='.$cursor)->assertConflict();
        $this->actingAs($this->tech, 'api');
        DB::table('employee_event')->where('event_id', $this->event->id)->delete();
        $this->getJson('/api/technician/sync/bootstrap?updated_since=2099-01-01T00:00:00Z')->assertOk()->assertJsonPath('data.revoked_event_ids.0', $this->event->id)->assertJsonPath('data.events.0.id', $second->id);
        $this->getJson('/api/technician/sync/bootstrap?cursor=invalid')->assertUnprocessable();
    }

    public function test_close_requires_required_steps_and_receipts_and_retries_return_original_response(): void
    {
        $this->actingAs($this->tech, 'api');
        $p = $this->protocol();
        $close = [...$this->times(), 'protocol_version' => $p['version'], 'notes' => 'Cierre administrativo'];
        $this->write('POST', $this->url('/close'), $close)->assertConflict()->assertJsonPath('code', 'REQUIRED_STEPS_PENDING');
        $this->write('POST', $this->url('/steps/'.$p['steps'][0]['id'].'/records'), $this->recordPayload($p['version']))->assertCreated();
        $this->write('POST', $this->url('/steps/'.$p['steps'][1]['id'].'/records'), $this->recordPayload($p['version']))->assertConflict()->assertJsonPath('code', 'RECEIPT_PENDING');
        $this->receive()->assertOk();
        foreach (array_slice($p['steps'], 1) as $step) {
            $this->write('POST', $this->url('/steps/'.$step['id'].'/records'), $this->recordPayload($p['version']))->assertCreated();
        }
        $key = (string) Str::uuid();
        $this->write('POST', $this->url('/close'), $close)->assertConflict()->assertJsonPath('code', 'MATERIALS_PENDING');
        $this->write('POST', $this->url('/return-declarations'), [...$this->times(), 'dispatch_id' => $this->dispatch, 'expected_revision' => 1, 'items' => [['dispatch_item_id' => $this->line, 'quantity_consumed' => '3.000', 'quantity_returned' => '0.000', 'quantity_damaged' => '0.000', 'quantity_lost' => '0.000']]])->assertCreated();
        $first = $this->write('POST', $this->url('/close'), $close, $key)->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.logistics_status', 'awaiting_return')->json();
        $this->write('POST', $this->url('/close'), $close, $key)->assertOk()->assertExactJson($first);
        $this->getJson($this->url('/protocol'))->assertOk()->assertJsonPath('data.status', 'closed');
        $this->getJson('/api/technician/events?status=completed')->assertOk()->assertJsonPath('meta.total', 1);
        $this->write('PATCH', '/api/technician/step-records/1', ['expected_revision' => 1, 'correction_reason' => 'Ya cerrado'])->assertConflict()->assertJsonPath('code', 'PROTOCOL_CLOSED');
    }

    public function test_replaying_a_synced_operation_after_assignment_revocation_is_forbidden(): void
    {
        $this->actingAs($this->tech, 'api');
        $p = $this->protocol();
        $key = (string) Str::uuid();
        $url = $this->url('/steps/'.$p['steps'][0]['id'].'/records');
        $payload = $this->recordPayload($p['version']);
        $this->write('POST', $url, $payload, $key)->assertCreated();
        DB::table('employee_event')->where('event_id', $this->event->id)->delete();
        $this->write('POST', $url, $payload, $key)->assertForbidden();
        $this->assertDatabaseCount('mobile_step_records', 1);
    }

    public function test_mobile_migration_can_resume_a_missing_index_and_preserves_snapshots(): void
    {
        $protocol = DB::table('mobile_event_protocols')->where('event_id', $this->event->id)->first();
        \Illuminate\Support\Facades\Schema::table('mobile_return_declaration_items', fn ($table) => $table->dropUnique('mobile_declaration_item_unique'));
        $migration = require database_path('migrations/2026_10_10_150000_create_mobile_operations.php');
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('mobile_return_declaration_items', ['declaration_id', 'dispatch_item_id'], 'unique'));
        $this->assertDatabaseHas('mobile_event_protocols', ['id' => $protocol->id, 'event_id' => $this->event->id]);
        $this->assertDatabaseCount('mobile_protocol_steps', 5);
    }

    public function test_mobile_rate_limit_includes_retry_after(): void
    {
        $this->actingAs($this->other, 'api');
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/technician/events')->assertOk();
        }
        $this->getJson('/api/technician/events')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_critical_incident_blocks_closure_and_legacy_employee_role_can_record_when_assigned(): void
    {
        $legacy = User::factory()->create(['role_id' => 4, 'phone' => '3121111111']);
        $this->assign($legacy, $this->event);
        $this->actingAs($legacy, 'api');
        $p = $this->protocol();
        foreach ($p['steps'] as $step) {
            $this->write('POST', $this->url('/steps/'.$step['id'].'/records'), $this->recordPayload($p['version']))->assertCreated();
        }
        $this->actingAs($this->tech, 'api');
        $this->receive()->assertOk();
        $this->write('POST', $this->url('/incidents'), [...$this->times(), 'category' => 'safety', 'severity' => 'critical', 'description' => 'Requiere revisión del responsable'])->assertCreated();
        $this->write('POST', $this->url('/close'), [...$this->times(), 'protocol_version' => $p['version'], 'notes' => 'Cierre con comentarios'])->assertConflict()->assertJsonPath('code', 'CRITICAL_INCIDENTS_PENDING');
    }

    public function test_materials_summary_legacy_listing_and_pending_pass_have_no_issuance_side_effects(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.materials_status', 'ready_to_receive')->assertJsonPath('data.materials_summary.required_lines', 1)->assertJsonPath('data.materials_summary.prepared_lines', 1)->assertJsonPath('data.materials_summary.delivered_lines', 1)->assertJsonPath('data.materials_summary.verified_lines', 0)->assertJsonPath('data.permissions.report_incident', true);
        $this->getJson('/api/employee-events')->assertOk()->assertJsonPath('data.events.0.materials_status', 'ready_to_receive');
        $this->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'blocked')->assertJsonPath('data.folio', null);
        $this->assertDatabaseCount('event_departure_passes', 0);
        $this->receive()->assertOk();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.materials_status', 'received')->assertJsonPath('data.materials_summary.verified_lines', 1);
        $this->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'not_issued');
        $this->actingAs($this->other, 'api')->getJson($this->url('/departure-pass'))->assertForbidden();
        $this->assertDatabaseCount('event_departure_passes', 0);
    }

    public function test_receipt_version_actor_identity_legacy_transition_and_retries_are_enforced(): void
    {
        $this->actingAs($this->tech, 'api');
        $url = '/api/technician/dispatches/'.$this->dispatch.'/receive';
        $payload = ['items' => [['dispatch_item_id' => $this->line, 'received_quantity' => '3.000', 'status' => 'received']]];
        $key = (string) Str::uuid();
        $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertConflict()->assertJsonPath('code', 'DISPATCH_VERSION_CONFLICT');
        $this->postJson($url, ['expected_version' => 1, ...$payload], ['Idempotency-Key' => $key])->assertConflict();
        $this->postJson($url, ['expected_version' => 2, ...$payload], ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('version', 3);
        $this->postJson($url, ['expected_version' => 2, ...$payload], ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('version', 3);
        $this->postJson($url, ['expected_version' => 2, 'notes' => 'Otro contenido', ...$payload], ['Idempotency-Key' => $key])->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_CONFLICT');
        $this->assertDatabaseCount('technician_receipts', 1);
        $this->event = $this->makeEvent();
        $this->assign($this->tech, $this->event);
        $this->event->products()->attach($this->product->id, ['quantity' => 3, 'price' => 100]);
        $this->prepareDispatch();
        InventoryData::update('event_dispatches', $this->dispatch, ['dispatched_by' => $this->tech->id]);
        $this->receive()->assertForbidden();
        InventoryData::update('event_dispatches', $this->dispatch, ['dispatched_by' => $this->admin->id, 'version' => null, 'content_hash' => null]);
        $payload['items'][0]['dispatch_item_id'] = $this->line;
        $this->postJson('/api/technician/dispatches/'.$this->dispatch.'/receive', $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('version', null);
    }

    public function test_official_pass_is_authorized_versioned_audited_revocable_and_expires(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->receive()->assertOk();
        $url = '/api/events/'.$this->event->id.'/departure-passes';
        $payload = ['technician_id' => $this->tech->id, 'expires_at' => now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'), 'dispatches' => [['id' => $this->dispatch, 'version' => 3]]];
        $key = (string) Str::uuid();
        $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertForbidden();
        $this->actingAs($this->admin, 'api');
        $this->postJson($url, [...$payload, 'dispatches' => [['id' => $this->dispatch, 'version' => 2]]], ['Idempotency-Key' => $key])->assertConflict();
        $pass = $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('data.status', 'valid')->json('data');
        $this->assertNotEmpty($pass['folio']);
        $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('data.id', $pass['id']);
        $this->assertDatabaseCount('event_departure_passes', 1);
        $this->assertDatabaseHas('inventory_audits', ['action' => 'departure_pass_issued', 'entity_id' => $pass['id']]);
        $this->actingAs($this->tech, 'api')->getJson($this->url())->assertOk()->assertJsonPath('data.materials_status', 'departure_authorized');
        $this->getJson('/api/technician/sync/bootstrap')->assertOk()->assertJsonPath('data.departure_passes.0.status', 'valid');
        $this->travel(61)->minutes();
        $this->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'expired');
        $this->actingAs($this->admin, 'api');
        $revokeUrl = $url.'/'.$pass['id'].'/revoke';
        $revoke = ['expected_version' => 1, 'reason' => 'Carga retenida para nueva revisión'];
        $key = (string) Str::uuid();
        $this->postJson($revokeUrl, $revoke, ['Idempotency-Key' => $key])->assertOk();
        $this->postJson($revokeUrl, $revoke, ['Idempotency-Key' => $key])->assertOk();
        $this->actingAs($this->tech, 'api')->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'revoked');
        $timeline = $this->getJson($this->url('/timeline'))->assertOk()->json('data');
        $this->assertContains('departure_pass_revoked', array_column($timeline, 'entity_type'));
        foreach ($timeline as $entry) {
            $this->assertNotEmpty($entry['actor']['name']);
        }
    }

    public function test_pass_blocks_discrepancies_and_detects_changed_load_without_writing_on_get(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->receive('2.000', 'quantity_mismatch')->assertOk();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.materials_status', 'has_discrepancies')->assertJsonPath('data.materials_summary.open_discrepancies', 1);
        $this->actingAs($this->admin, 'api')->postJson('/api/events/'.$this->event->id.'/departure-passes', ['technician_id' => $this->tech->id, 'expires_at' => now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'), 'dispatches' => [['id' => $this->dispatch, 'version' => 3]]], ['Idempotency-Key' => (string) Str::uuid()])->assertConflict()->assertJsonPath('code', 'DEPARTURE_PASS_BLOCKED');
        $this->assertDatabaseCount('event_departure_passes', 0);
        $this->actingAs($this->tech, 'api')->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'blocked');
        InventoryData::update('event_dispatch_items', $this->line, ['quantity' => 4]);
        $this->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->assertDatabaseCount('event_departure_passes', 0);
    }

    public function test_serialized_units_are_separate_and_receipt_and_return_require_the_correct_series(): void
    {
        $this->event = $this->makeEvent();
        $this->assign($this->tech, $this->event);
        $this->product->forceFill(['is_returnable' => true])->save();
        $this->event->products()->attach($this->product->id, ['quantity' => 3, 'price' => 100]);
        $this->prepareDispatch(false);
        $this->actingAs($this->tech, 'api')->getJson($this->url('/dispatch'))->assertOk()->assertJsonPath('dispatches.0.dispatch.status', 'prepared')->assertJsonPath('dispatches.0.dispatch.version', 1);
        $this->receive()->assertUnprocessable();
        $identityUrl = '/api/event-dispatches/'.$this->dispatch.'/identity';
        $this->actingAs($this->admin, 'api')->putJson($identityUrl, ['expected_version' => 1, 'items' => [['dispatch_item_id' => $this->line, 'serial_numbers' => ['SR-001', 'SR-002', 'SR-003']]]], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('dispatch.version', 2);
        app(EventDispatchService::class)->confirm($this->dispatch, $this->admin->id);
        $this->actingAs($this->tech, 'api');
        $snapshot = $this->getJson($this->url('/dispatch'))->assertOk()->assertJsonPath('dispatches.0.dispatch.warehouse_user.display_name', $this->admin->name)->json('dispatches.0');
        $payload = ['expected_version' => 3, 'items' => array_map(fn ($item) => ['dispatch_item_id' => $item['id'], 'received_quantity' => '1.000', 'status' => 'received', 'serial_number' => $item['serial_number']], $snapshot['items'])];
        $receiveUrl = '/api/technician/dispatches/'.$this->dispatch.'/receive';
        $wrong = $payload;
        $wrong['items'][0]['serial_number'] = 'SERIE-AJENA';
        $this->postJson($receiveUrl, $wrong, ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $movements = DB::table('inventory_movements')->count();
        $this->postJson($receiveUrl, $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('version', 4);
        $this->getJson($this->url('/dispatch'))->assertOk()->assertJsonPath('dispatches.0.receipt.items.0.serial_number', 'SR-001');
        $line = ['dispatch_item_id' => $snapshot['items'][0]['id'], 'quantity_consumed' => '0.000', 'quantity_returned' => '0.000', 'quantity_damaged' => '1.000', 'quantity_lost' => '0.000', 'serial_number' => 'WRONG', 'notes' => 'Carcasa dañada'];
        $data = [...$this->times(), 'dispatch_id' => $this->dispatch, 'expected_revision' => 1, 'items' => [$line]];
        $this->write('POST', $this->url('/return-declarations'), $data)->assertUnprocessable();
        $line['serial_number'] = 'SR-001';
        $this->write('POST', $this->url('/return-declarations'), [...$data, 'items' => [[...$line, 'notes' => ' ']]])->assertUnprocessable();
        $this->write('POST', $this->url('/return-declarations'), [...$data, 'items' => [$line]])->assertCreated();
        $this->assertSame($movements, DB::table('inventory_movements')->count());
        $this->getJson($this->url('/return-declarations'))->assertOk()->assertJsonPath('data.0.items.0.serial_number', 'SR-001')->assertJsonPath('reconciliations.0.items.1.quantity_pending', '1.000');
        $this->actingAs($this->admin, 'api')->putJson($identityUrl, ['expected_version' => 4, 'items' => [['dispatch_item_id' => $this->line, 'serial_numbers' => ['OTRA']]]])->assertConflict()->assertJsonPath('code', 'DISPATCH_IMMUTABLE');
        $quarantine = InventoryData::insert('inventory_locations', ['name' => 'Cuarentena', 'code' => 'QUAR', 'type' => 'quarantine']);
        $warehouseItems = array_map(fn ($item) => ['dispatch_item_id' => $item['id'], 'serial_number' => $item['serial_number'], 'quantity_consumed' => '0.000', 'quantity_returned' => $item['serial_number'] === 'SR-001' ? '0.000' : '1.000', 'quantity_damaged' => $item['serial_number'] === 'SR-001' ? '1.000' : '0.000', 'quantity_lost' => '0.000'], $snapshot['items']);
        $return = $this->postJson('/api/events/'.$this->event->id.'/returns', ['event_dispatch_id' => $this->dispatch, 'warehouse_location_id' => $this->warehouse, 'damaged_location_id' => $quarantine, 'items' => $warehouseItems])->assertOk()->json('return.id');
        $key = (string) Str::uuid();
        $this->postJson('/api/event-returns/'.$return.'/confirm', [], ['Idempotency-Key' => $key])->assertOk();
        $afterReturn = DB::table('inventory_movements')->count();
        $this->postJson('/api/event-returns/'.$return.'/confirm', [], ['Idempotency-Key' => $key])->assertOk();
        $this->assertSame($afterReturn, DB::table('inventory_movements')->count());
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product->id, 'location_id' => $quarantine, 'quantity' => 1]);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product->id, 'location_id' => $this->warehouse, 'quantity' => 6]);
        $this->actingAs($this->tech, 'api')->getJson($this->url('/return-declarations'))->assertOk()->assertJsonPath('data.0.status', 'warehouse_confirmed')->assertJsonPath('reconciliations.0.items.1.quantity_pending', '0.000')->assertJsonPath('reconciliations.0.items.1.warehouse_quantities.quantity_returned', '1.000');
    }

    public function test_forty_five_serialized_lines_stay_separate_across_multiple_dispatches_and_pass_covers_all(): void
    {
        $this->event = $this->makeEvent();
        $this->assign($this->tech, $this->event);
        $this->event->products()->attach($this->product->id, ['quantity' => 45, 'price' => 100]);
        $secondWarehouse = InventoryData::insert('inventory_locations', ['name' => 'Segundo almacén', 'code' => 'SECOND', 'type' => 'warehouse']);
        app(InventoryMovementService::class)->record($this->product->id, 13, null, $this->warehouse, 'adjustment_in', $this->admin->id, 'Apertura adicional');
        app(InventoryMovementService::class)->record($this->product->id, 25, null, $secondWarehouse, 'adjustment_in', $this->admin->id, 'Apertura');
        app(EventRequirementService::class)->snapshot($this->event, $this->admin->id);
        $requirement = DB::table('event_requirements')->where('event_id', $this->event->id)->value('id');
        $pickings = [];
        foreach ([$this->warehouse => 20, $secondWarehouse => 25] as $location => $quantity) {
            app(RequirementAllocationService::class)->allocate($this->event->id, $requirement, ['warehouse_location_id' => $location, 'allocations' => [['product_id' => $this->product->id, 'quantity' => $quantity]]], $this->admin);
            $picking = app(EventPickingService::class)->create($this->event->id, $location, $this->admin->id);
            foreach ($picking['items'] as $item) {
                app(EventPickingService::class)->confirm($this->event->id, $picking['picking']['id'], $item->id, (float) $item->quantity_required, $this->admin->id);
            }
            app(EventPickingService::class)->complete($this->event->id, $picking['picking']['id'], $this->admin->id);
            $pickings[] = $picking;
        }
        app(EventPreparationService::class)->transition($this->event->id, true, $this->admin->id);
        foreach ($pickings as $picking) {
            $dispatch = app(EventDispatchService::class)->create($this->event->id, ['picking_id' => $picking['picking']['id'], 'technician_id' => $this->tech->id], $this->admin->id);
            $serials = array_map(fn ($number) => 'SR-'.$dispatch['dispatch']['id'].'-'.$number, range(1, (int) $dispatch['items'][0]->quantity));
            app(EventDispatchService::class)->identify($dispatch['dispatch']['id'], ['expected_version' => 1, 'items' => [['dispatch_item_id' => $dispatch['items'][0]->id, 'serial_numbers' => $serials]]], $this->admin->id);
            app(EventDispatchService::class)->confirm($dispatch['dispatch']['id'], $this->admin->id);
        }
        $this->actingAs($this->tech, 'api');
        $dispatches = $this->getJson($this->url('/dispatch'))->assertOk()->assertJsonCount(2, 'dispatches')->json('dispatches');
        $ids = array_merge(array_column($dispatches[0]['items'], 'id'), array_column($dispatches[1]['items'], 'id'));
        $this->assertCount(45, array_unique($ids));
        $movementCount = DB::table('inventory_movements')->count();
        $expected = [];
        foreach ($dispatches as $dispatch) {
            $items = array_map(fn ($item) => ['dispatch_item_id' => $item['id'], 'received_quantity' => '1.000', 'status' => 'received', 'serial_number' => $item['serial_number']], $dispatch['items']);
            $payload = ['expected_version' => $dispatch['dispatch']['version'], 'items' => $items];
            $wrong = $payload;
            $wrong['items'][0]['dispatch_item_id'] = $dispatch['dispatch']['id'] === $dispatches[0]['dispatch']['id'] ? $dispatches[1]['items'][0]['id'] : $dispatches[0]['items'][0]['id'];
            $url = '/api/technician/dispatches/'.$dispatch['dispatch']['id'].'/receive';
            $this->postJson($url, $wrong, ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
            $key = (string) Str::uuid();
            $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertOk();
            $this->postJson($url, $payload, ['Idempotency-Key' => $key])->assertOk();
            $expected[] = ['id' => $dispatch['dispatch']['id'], 'version' => 4];
        }
        $this->assertSame($movementCount, DB::table('inventory_movements')->count());
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.materials_summary.delivered_lines', 45)->assertJsonPath('data.materials_summary.verified_lines', 45);
        $this->actingAs($this->admin, 'api');
        $url = '/api/events/'.$this->event->id.'/departure-passes';
        $payload = ['technician_id' => $this->tech->id, 'expires_at' => now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'), 'dispatches' => [$expected[0]]];
        $this->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertConflict()->assertJsonPath('code', 'DISPATCH_VERSION_CONFLICT');
        $this->postJson($url, [...$payload, 'dispatches' => $expected], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('data.status', 'valid')->assertJsonCount(2, 'data.dispatches');
        InventoryData::update('event_dispatch_items', $ids[0], ['serial_number' => 'SERIE-CAMBIADA']);
        $this->actingAs($this->tech, 'api')->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->assertDatabaseCount('event_departure_passes', 1);
    }

    public function test_an_incident_revokes_an_issued_pass_once_without_changing_inventory(): void
    {
        $this->actingAs($this->tech, 'api');
        $this->receive()->assertOk();
        $this->actingAs($this->admin, 'api')->postJson('/api/events/'.$this->event->id.'/departure-passes', ['technician_id' => $this->tech->id, 'expires_at' => now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'), 'dispatches' => [['id' => $this->dispatch, 'version' => 3]]], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('data.status', 'valid');
        $movements = DB::table('inventory_movements')->count();
        $this->actingAs($this->tech, 'api');
        $payload = [...$this->times(), 'category' => 'damaged_item', 'severity' => 'medium', 'dispatch_item_id' => $this->line, 'description' => 'Daño encontrado después de recibir', 'attachment_ids' => []];
        $key = (string) Str::uuid();
        $id = $this->write('POST', $this->url('/incidents'), $payload, $key)->assertCreated()->json('data.id');
        $this->write('POST', $this->url('/incidents'), $payload, $key)->assertCreated()->assertJsonPath('data.id', $id);
        $this->getJson($this->url('/departure-pass'))->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->assertSame(1, DB::table('inventory_audits')->where('action', 'departure_pass_revoked')->count());
        $this->assertSame($movements, DB::table('inventory_movements')->count());
    }

    public function test_override_requires_explicit_supervisor_permission_and_current_assignment(): void
    {
        $this->actingAs($this->tech, 'api');
        $p = $this->protocol();
        $url = $this->url('/steps/'.$p['steps'][0]['id'].'/override');
        $data = ['protocol_version' => $p['version'], 'reason' => 'Excepción administrativa autorizada'];
        $this->write('POST', $url, $data)->assertForbidden();
        $supervisor = User::factory()->create(['role_id' => 3, 'phone' => '3121111111']);
        $this->assign($supervisor, $this->event);
        $this->actingAs($supervisor, 'api');
        $this->write('POST', $url, $data)->assertForbidden();
        DB::table('inventory_permissions')->insert(['user_id' => $supervisor->id, 'permission' => 'mobile.override']);
        $this->write('POST', $url, $data)->assertCreated();
        $this->getJson($this->url('/protocol'))->assertOk()->assertJsonCount(1, 'data.steps.0.overrides');
        $this->assertDatabaseCount('mobile_step_overrides', 1);
    }
}
