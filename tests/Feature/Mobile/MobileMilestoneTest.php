<?php

namespace Tests\Feature\Mobile;

use App\Models\Event;
use App\Models\Package;
use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Mobile\MobileProtocolUpgradeService;
use App\Services\Mobile\TechnicianEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tech;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        DB::table('roles')->insert([['id' => 1, 'name' => 'admin', 'description' => 'Administrador'], ['id' => 2, 'name' => 'technician', 'description' => 'Técnico']]);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121000001']);
        $this->tech = User::factory()->create(['role_id' => 2, 'phone' => '3121000002']);
        $level = InventoryData::insert('experience_levels', ['name' => 'Técnico', 'description' => 'Autorizado']);
        $employee = InventoryData::insert('employees', ['name' => $this->tech->name, 'email' => $this->tech->email, 'phone' => $this->tech->phone, 'salary' => '100', 'experience_level_id' => $level, 'user_id' => $this->tech->id]);
        $package = Package::create(['name' => 'Boda', 'description' => 'Espectáculo', 'price' => 5000, 'duration' => '15 min']);
        $this->event = Event::create(['package_id' => $package->id, 'date' => now()->toDateString(), 'phone' => '3121000003', 'client_name' => 'María', 'event_address' => 'Salón', 'event_date' => now()->addDay()]);
        DB::table('employee_event')->insert(['employee_id' => $employee, 'event_id' => $this->event->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->tech, 'api');
    }

    private function url(string $suffix = ''): string
    {
        return '/api/technician/events/'.$this->event->id.$suffix;
    }

    private function payload(int $version = 2): array
    {
        return ['protocol_version' => $version, 'occurred_at' => now()->subMinutes(3)->utc()->format('Y-m-d\TH:i:s\Z'), 'recorded_at' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z'), 'capture_mode' => 'manual', 'manual_reason' => 'Registro posterior por falta de señal', 'notes' => 'Actividad realizada y revisada'];
    }

    private function write(string $suffix, array $payload, ?string $key = null)
    {
        $key ??= (string) Str::uuid();

        return $this->postJson($this->url($suffix), ['client_operation_id' => $key, ...$payload], ['Idempotency-Key' => $key]);
    }

    private function legacy(): object
    {
        $definition = config('mobile.protocol');
        config(['mobile.protocol.version' => 1, 'mobile.protocol.steps' => [
            ['title' => 'Llegada al lugar', 'instructions' => 'Llegada', 'required' => true],
            ['title' => 'Recepción revisada', 'instructions' => 'Recepción', 'required' => true],
            ['title' => 'Conclusión de actividades', 'instructions' => 'Conclusión', 'required' => true],
        ]]);
        $protocol = app(TechnicianEventService::class)->snapshot($this->event->id);
        config(['mobile.protocol' => $definition]);

        return $protocol;
    }

    public function test_four_coded_milestones_have_confirmed_times_and_operational_progress_without_stock_changes(): void
    {
        $protocol = $this->getJson($this->url('/protocol'))->assertOk()->assertJsonPath('data.status', 'not_started')->json('data');
        $this->assertSame(['arrived', null, 'installed', 'detonated', 'finished'], array_column($protocol['steps'], 'milestone_code'));
        foreach ($protocol['steps'] as $step) {
            $this->write('/steps/'.$step['id'].'/records', $this->payload())->assertCreated();
            if ($step['milestone_code']) {
                $this->getJson($this->url())->assertOk()->assertJsonPath('data.operational_status', $step['milestone_code'])->assertJsonPath('data.milestones.'.$step['milestone_code'].'.occurred_at', $this->payload()['occurred_at']);
                $this->assertDatabaseHas('events', ['id' => $this->event->id, 'operational_status' => $step['milestone_code']]);
            }
            $this->getJson($this->url('/protocol'))->assertOk()->assertJsonPath('data.status', 'in_progress');
        }
        $this->assertDatabaseHas('events', ['id' => $this->event->id, 'logistics_status' => 'pending']);
        $close = collect($this->payload())->except(['capture_mode', 'manual_reason'])->all();
        $this->write('/close', $close)->assertOk()->assertJsonPath('data.operational_status', 'finished');
        $this->getJson($this->url('/protocol'))->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.closure.notes', $close['notes'])->assertJsonPath('data.closure.occurred_at', $close['occurred_at']);
        $this->assertDatabaseHas('mobile_event_protocols', ['event_id' => $this->event->id, 'closing_notes' => $close['notes']]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_milestones_and_closure_require_nonempty_comments_including_corrections(): void
    {
        $protocol = $this->getJson($this->url('/protocol'))->json('data');
        $step = $protocol['steps'][0]['id'];
        foreach ([null, '', '   '] as $notes) {
            $this->write('/steps/'.$step.'/records', [...$this->payload(), 'notes' => $notes])->assertUnprocessable()->assertJsonValidationErrors('notes');
            $this->write('/close', [...$this->payload(), 'notes' => $notes])->assertUnprocessable()->assertJsonValidationErrors('notes');
        }
        $this->assertDatabaseCount('mobile_step_records', 0);
        $record = $this->write('/steps/'.$step.'/records', $this->payload())->assertCreated()->json('data');
        $key = (string) Str::uuid();
        $this->patchJson('/api/technician/step-records/'.$record['id'], ['client_operation_id' => $key, 'expected_revision' => 1, 'notes' => ' ', 'correction_reason' => 'Corrección'], ['Idempotency-Key' => $key])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->assertDatabaseCount('mobile_record_corrections', 0);
    }

    public function test_receipt_rule_survives_reordering_and_renaming_of_the_step(): void
    {
        $protocol = $this->getJson($this->url('/protocol'))->json('data');
        $this->write('/steps/'.$protocol['steps'][0]['id'].'/records', $this->payload())->assertCreated();
        $receipt = $protocol['steps'][1]['id'];
        InventoryData::update('mobile_protocol_steps', $receipt, ['sort_order' => 10, 'title' => 'Verificación del material recibido']);
        $location = InventoryData::insert('inventory_locations', ['name' => 'Polvorín', 'code' => 'POL', 'type' => 'warehouse']);
        $picking = InventoryData::insert('event_pickings', ['event_id' => $this->event->id, 'warehouse_location_id' => $location, 'started_by' => $this->admin->id, 'started_at' => now()]);
        $dispatch = InventoryData::insert('event_dispatches', ['event_id' => $this->event->id, 'picking_id' => $picking, 'warehouse_location_id' => $location, 'destination_location_id' => $location, 'technician_id' => $this->tech->id, 'status' => 'confirmed']);
        $this->write('/steps/'.$receipt.'/records', $this->payload())->assertConflict()->assertJsonPath('code', 'RECEIPT_PENDING');
        InventoryData::update('event_dispatches', $dispatch, ['status' => 'received']);
        $this->write('/steps/'.$receipt.'/records', $this->payload())->assertCreated();
    }

    public function test_explicit_upgrade_keeps_ids_records_uuids_and_rejects_unsent_old_versions(): void
    {
        $protocol = $this->legacy();
        $originalSteps = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('sort_order')->get();
        $key = (string) Str::uuid();
        $original = $this->write('/steps/'.$originalSteps[0]->id.'/records', $this->payload(1), $key)->assertCreated()->json();
        $this->getJson($this->url('/protocol'))->assertJsonCount(3, 'data.steps')->assertJsonPath('data.version', 1);
        $this->actingAs($this->admin, 'web');
        $this->get('/settings/events/'.$this->event->id.'/protocol-upgrade')->assertOk()->assertSee('Aplicar actualización auditada');
        Artisan::call('mobile:upgrade-protocol', ['event' => $this->event->id]);
        $this->assertDatabaseCount('mobile_protocol_steps', 3);
        $this->post('/settings/events/'.$this->event->id.'/protocol-upgrade', ['expected_version' => 1, 'reason' => 'Habilitar los nuevos hitos'])->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson($this->url('/protocol'))->assertJsonCount(5, 'data.steps')->assertJsonPath('data.version', 2);
        foreach ($originalSteps as $step) {
            $this->assertDatabaseHas('mobile_protocol_steps', ['id' => $step->id, 'protocol_id' => $protocol->id]);
        }
        $this->assertDatabaseHas('mobile_step_records', ['id' => $original['data']['id'], 'client_operation_id' => $key, 'protocol_version' => 1]);
        $this->assertDatabaseHas('inventory_audits', ['action' => 'mobile_protocol_upgraded', 'actor_id' => $this->admin->id]);
        $this->write('/steps/'.$originalSteps[1]->id.'/records', $this->payload(1))->assertConflict()->assertJsonPath('code', 'PROTOCOL_VERSION_CONFLICT');
        $this->write('/steps/'.$originalSteps[0]->id.'/records', $this->payload(1), $key)->assertCreated()->assertExactJson($original);
        $this->write('/steps/'.$originalSteps[1]->id.'/records', $this->payload(2))->assertCreated();
        $steps = $this->getJson($this->url('/protocol'))->json('data.steps');
        foreach (array_slice($steps, 2) as $step) {
            $this->write('/steps/'.$step['id'].'/records', $this->payload(2))->assertCreated();
        }
        $this->assertDatabaseCount('mobile_step_records', 5);
    }

    public function test_upgrade_is_atomic_authorized_and_leaves_completed_history_unchanged(): void
    {
        $protocol = $this->legacy();
        $this->actingAs($this->tech, 'web')->get('/settings/events/'.$this->event->id.'/protocol-upgrade')->assertForbidden();
        $this->post('/settings/events/'.$this->event->id.'/protocol-upgrade', ['expected_version' => 1, 'reason' => 'No autorizado'])->assertForbidden();
        $this->actingAs($this->admin, 'web');
        $this->postJson('/settings/events/'.$this->event->id.'/protocol-upgrade', ['expected_version' => 0, 'reason' => 'Versión obsoleta'])->assertConflict()->assertJsonPath('code', 'PROTOCOL_VERSION_CONFLICT');
        foreach ($this->getJson($this->url('/protocol'))->json('data.steps') as $step) {
            $this->write('/steps/'.$step['id'].'/records', $this->payload(1))->assertCreated();
        }
        $this->actingAs($this->admin, 'web');
        $this->postJson('/settings/events/'.$this->event->id.'/protocol-upgrade', ['expected_version' => 1, 'reason' => 'Historial concluido'])->assertConflict()->assertJsonPath('code', 'PROTOCOL_UPGRADE_BLOCKED');
        $this->assertDatabaseHas('mobile_event_protocols', ['id' => $protocol->id, 'version' => 1]);
        $this->assertDatabaseCount('mobile_protocol_steps', 3);
        $this->assertDatabaseCount('mobile_step_records', 3);
    }

    public function test_upgrade_preserves_additional_steps_dependencies_and_is_repeatable(): void
    {
        $protocol = $this->legacy();
        $finish = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->where('sort_order', 3)->first();
        InventoryData::update('mobile_protocol_steps', $finish->id, ['sort_order' => 4]);
        $additional = InventoryData::insert('mobile_protocol_steps', ['protocol_id' => $protocol->id, 'sort_order' => 3, 'title' => 'Revisión adicional', 'instructions' => 'Paso configurado por el panel', 'required' => true, 'capture_mode' => 'automatic', 'allowed_capture_modes' => ['automatic'], 'allow_notes' => true, 'allowed_evidence' => [], 'dependencies' => InventoryData::json($finish->dependencies)]);
        InventoryData::update('mobile_protocol_steps', $finish->id, ['dependencies' => [$additional]]);
        $service = app(MobileProtocolUpgradeService::class);
        $this->assertTrue($service->apply($this->event->id, $this->admin, 1, 'Conservar revisión adicional')['updated']);
        $steps = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('sort_order')->get();
        $this->assertCount(6, $steps);
        $installed = $steps->firstWhere('milestone_code', 'installed');
        $this->assertSame([$additional], InventoryData::json($installed->dependencies));
        $this->assertDatabaseHas('mobile_protocol_steps', ['id' => $additional, 'title' => 'Revisión adicional', 'sort_order' => 3]);
        $this->assertFalse($service->apply($this->event->id, $this->admin, 2, 'Revisión repetida')['updated']);
        $this->assertSame(1, DB::table('inventory_audits')->where('action', 'mobile_protocol_upgraded')->count());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_schema_migration_resumes_without_changing_legacy_protocols_or_records(): void
    {
        $protocol = $this->legacy();
        $step = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('sort_order')->value('id');
        $this->write('/steps/'.$step.'/records', $this->payload(1))->assertCreated();
        $before = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('id')->get()->toJson();
        \Illuminate\Support\Facades\Schema::table('mobile_protocol_steps', fn ($table) => $table->dropUnique('mobile_protocol_milestone_unique'));
        $migration = require database_path('migrations/2026_10_10_180000_add_mobile_milestone_contract.php');
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('mobile_protocol_steps', 'mobile_protocol_milestone_unique'));
        $this->assertSame($before, DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('mobile_event_protocols', ['id' => $protocol->id, 'version' => 1]);
        $this->assertDatabaseCount('mobile_step_records', 1);
    }

    public function test_closing_before_last_milestone_time_is_rejected(): void
    {
        $protocol = $this->getJson($this->url('/protocol'))->json('data');
        foreach ($protocol['steps'] as $step) {
            $this->write('/steps/'.$step['id'].'/records', $this->payload())->assertCreated();
        }
        $this->write('/close', [...$this->payload(), 'occurred_at' => now()->subMinutes(4)->utc()->format('Y-m-d\TH:i:s\Z')])->assertConflict()->assertJsonPath('code', 'STEP_ORDER_CONFLICT');
        $this->assertDatabaseHas('mobile_event_protocols', ['event_id' => $this->event->id, 'status' => 'in_progress']);
    }
}
