<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;

class TechnicianEventService
{
    public function assignedIds(int $actor): \Illuminate\Database\Query\Builder
    {
        return DB::table('employee_event')->join('employees', 'employees.id', '=', 'employee_event.employee_id')->where('employees.user_id', $actor)->where('employees.status', 'active')->select('employee_event.event_id');
    }

    public function authorize(User $actor, int $event, ?string $permission = null, bool $lock = false): object
    {
        $row = InventoryData::get('events', $event, $lock);
        abort_unless($actor->status === 'active', 403, 'La cuenta está desactivada.');
        abort_unless(DB::query()->fromSub($this->assignedIds($actor->id), 'assigned')->where('event_id', $event)->exists(), 403, 'No tienes una asignación vigente en este evento.');
        if ($permission) {
            MobileContract::conflict($row->status !== 'cancelled', 'El evento está cancelado.', 'EVENT_CANCELLED');
            app(InventoryAccess::class)->authorize($actor, $permission);
        }

        return $row;
    }

    public function snapshot(int $event): object
    {
        // Existing snapshots are immutable; ordinary reads do not need an event row lock.
        $existing = DB::table('mobile_event_protocols')->where('event_id', $event)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($event) {
            $row = InventoryData::get('events', $event, true);
            $existing = DB::table('mobile_event_protocols')->where('event_id', $event)->first();
            if ($existing) {
                return $existing;
            }
            $packages = DB::table('event_package')->join('packages', 'packages.id', '=', 'event_package.package_id')->where('event_id', $event)->select('packages.id', 'packages.name', 'packages.description')->get()->map(fn ($p) => (array) $p)->all();
            if (! $packages && $row->package_id) {
                $package = DB::table('packages')->where('id', $row->package_id)->first(['id', 'name', 'description']);
                if ($package) {
                    $packages = [(array) $package];
                }
            }
            $definition = config('mobile.protocol');
            $id = InventoryData::insert('mobile_event_protocols', ['event_id' => $event, 'version' => $definition['version'], 'title' => $definition['title'], 'status' => 'not_started', 'package_snapshot' => ['packages' => $packages]]);
            $previous = null;
            foreach ($definition['steps'] as $order => $step) {
                $previous = InventoryData::insert('mobile_protocol_steps', ['protocol_id' => $id, 'sort_order' => $order + 1, 'milestone_code' => $step['milestone_code'] ?? null, 'validation_rule' => $step['validation_rule'] ?? null, 'requires_notes' => $step['requires_notes'] ?? false, 'title' => $step['title'], 'instructions' => $step['instructions'], 'required' => $step['required'], 'capture_mode' => 'automatic', 'allowed_capture_modes' => ['automatic', 'manual'], 'allow_notes' => true, 'allowed_evidence' => config('mobile.attachment_mimes'), 'dependencies' => $previous ? [$previous] : []]);
            }

            return InventoryData::get('mobile_event_protocols', $id);
        });
    }

    public function progress(object $protocol): array
    {
        $steps = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->where('required', true)->pluck('id');
        $completed = DB::table('mobile_step_records')->whereIn('step_id', $steps)->pluck('step_id')->merge(DB::table('mobile_step_overrides')->whereIn('step_id', $steps)->pluck('step_id'))->unique()->count();

        return ['completed' => $completed, 'required' => $steps->count()];
    }

    public function summary(object $event, User $actor): array
    {
        DB::table('mobile_sync_seen_events')->updateOrInsert(['user_id' => $actor->id, 'event_id' => $event->id], ['created_at' => now(), 'updated_at' => now()]);
        $protocol = $this->snapshot($event->id);
        $receipts = DB::table('event_dispatches')->where('event_id', $event->id)->where('technician_id', $actor->id)->whereIn('status', ['confirmed', 'received', 'closed'])->pluck('id');
        $statuses = DB::table('technician_receipts')->whereIn('event_dispatch_id', $receipts)->pluck('status');

        $state = app(MobileProtocolService::class)->state($protocol);

        return ['operational_status' => $state['operational_status'], 'milestones' => $state['milestones'], 'protocol_status' => $state['protocol_status'], 'id' => $event->id, 'title' => 'Evento de '.$event->client_name, 'starts_at' => MobileContract::utc($event->event_date, config('mobile.event_timezone')), 'venue' => $event->event_address, 'logistics_status' => $event->logistics_status,
            'receipt_status' => $statuses->contains('quantity_mismatch') ? 'quantity_mismatch' : ($receipts->count() && $statuses->count() === $receipts->count() ? 'received' : 'pending'),
            'dispatch_status' => $receipts->isEmpty() ? 'pending_dispatch' : 'available',
            'protocol_progress' => $this->progress($protocol), 'assignment' => ['user_id' => $actor->id, 'role' => 'technician'], 'updated_at' => MobileContract::utc($event->updated_at)] + app(MobileMaterialsService::class)->summary($event->id, $actor);
    }

    public function detail(int $event, User $actor): array
    {
        $row = $this->authorize($actor, $event);
        $protocol = $this->snapshot($event);
        $packages = InventoryData::json($protocol->package_snapshot)['packages'];
        $access = app(InventoryAccess::class);

        $summary = $this->summary($row, $actor);
        $progress = $summary['protocol_progress'];

        return $summary + [
            'package_snapshot' => count($packages) === 1 ? $packages[0] : ['id' => null, 'name' => implode(', ', array_column($packages, 'name')) ?: 'Paquete personalizado', 'description' => null, 'packages' => $packages],
            'contacts' => $row->phone ? [['name' => $row->client_name, 'phone' => $row->phone]] : [],
            'requirements' => DB::table('event_requirements')->where('event_id', $event)->orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'requirement_type' => $r->requirement_type, 'quantity_required' => MobileContract::quantity(MobileContract::milli($r->quantity_required)), 'status' => $r->status, 'snapshot' => InventoryData::json($r->snapshot)])->all(),
            'dispatch_ids' => DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->whereIn('status', ['preparing', 'prepared', 'confirmed', 'received', 'closed'])->orderBy('id')->pluck('id')->all(),
            'protocol' => ['id' => $protocol->id, 'version' => $protocol->version],
            'permissions' => ['report_incident' => $access->allows($actor, 'mobile.incident'), 'receive_dispatch' => $access->allows($actor, 'mobile.receive') && DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->where('status', 'confirmed')->where(fn ($q) => $q->whereNull('dispatched_by')->orWhere('dispatched_by', '!=', $actor->id))->exists(), 'declare_return' => $access->allows($actor, 'mobile.return') && DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->where('status', 'received')->whereNotIn('id', DB::table('event_returns')->select('event_dispatch_id'))->exists(), 'record_steps' => $protocol->status !== 'closed' && $access->allows($actor, 'mobile.record'), 'close' => $protocol->status !== 'closed' && $access->allows($actor, 'mobile.close') && $progress['completed'] === $progress['required'] && ! app(MobileProtocolService::class)->hasClosureBlockers($event)],
        ];
    }

    public function protocol(int $event, User $actor): array
    {
        $this->authorize($actor, $event);
        $p = $this->snapshot($event);
        $records = DB::table('mobile_step_records')->where('event_id', $event)->get()->groupBy('step_id');
        $overrides = DB::table('mobile_step_overrides')->where('event_id', $event)->get()->groupBy('step_id');
        $canCorrect = app(InventoryAccess::class)->allows($actor, 'mobile.correct_own');
        $steps = DB::table('mobile_protocol_steps')->where('protocol_id', $p->id)->orderBy('sort_order')->get()->map(function ($s) use ($actor, $records, $overrides, $canCorrect) {
            return ['id' => $s->id, 'order' => $s->sort_order, 'milestone_code' => $s->milestone_code, 'validation_rule' => $s->validation_rule, 'requires_notes' => (bool) $s->requires_notes || app(MobileProtocolService::class)->milestone($s) !== null, 'title' => $s->title, 'instructions' => $s->instructions, 'required' => (bool) $s->required, 'capture_mode' => $s->capture_mode, 'allowed_capture_modes' => InventoryData::json($s->allowed_capture_modes), 'allow_notes' => (bool) $s->allow_notes, 'allowed_evidence' => InventoryData::json($s->allowed_evidence), 'responsible_user_id' => $s->responsible_user_id, 'dependencies' => InventoryData::json($s->dependencies),
                'records' => collect($records[$s->id] ?? [])->map(fn ($r) => $this->record($r, $actor, $canCorrect))->all(),
                'overrides' => collect($overrides[$s->id] ?? [])->map(fn ($r) => MobileContract::dates((array) $r))->all()];
        })->all();

        return ['event_id' => $event, 'protocol_id' => $p->id, 'version' => $p->version, 'title' => $p->title, 'status' => app(MobileProtocolService::class)->state($p)['protocol_status'], 'steps' => $steps, 'closure' => $p->closed_at ? ['notes' => $p->closing_notes, 'occurred_at' => MobileContract::utc($p->closure_occurred_at ?? $p->closed_at), 'recorded_at' => MobileContract::utc($p->closure_recorded_at ?? $p->closed_at), 'confirmed_at' => MobileContract::utc($p->closed_at), 'closed_by' => $p->closed_by] : null, 'permissions' => ['override_required_steps' => $this->canOverride($actor)]];
    }

    public function canOverride(User $actor): bool
    {
        $roles = $actor->roles()->pluck('name')->merge([$actor->role?->name]);

        return $roles->intersect(['root', 'admin', 'supervisor'])->isNotEmpty() && DB::table('inventory_permissions')->where('user_id', $actor->id)->where('permission', 'mobile.override')->exists();
    }

    public function record(object $record, User $actor, ?bool $canCorrect = null): array
    {
        $data = MobileContract::dates((array) $record);
        unset($data['original_payload']);
        $data['can_correct'] = $record->recorded_by === $actor->id && ($canCorrect ?? app(InventoryAccess::class)->allows($actor, 'mobile.correct_own'));

        return $data;
    }
}
