<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileOperationService
{
    private function context(int $event, User $actor, string $permission, ?int $version = null): object
    {
        app(TechnicianEventService::class)->authorize($actor, $event, $permission, true);
        $protocol = app(TechnicianEventService::class)->snapshot($event);
        if ($version !== null) {
            MobileContract::conflict($protocol->version === $version, 'El protocolo cambió.', 'PROTOCOL_VERSION_CONFLICT', ['protocol_version' => ['Actualiza el snapshot.']]);
        }
        MobileContract::conflict($protocol->status !== 'closed', 'La bitácora está cerrada.', 'PROTOCOL_CLOSED');

        return $protocol;
    }

    public function times(array $data): array
    {
        $occurred = \Carbon\CarbonImmutable::parse($data['occurred_at']);
        $recorded = \Carbon\CarbonImmutable::parse($data['recorded_at']);
        if ($recorded->lt($occurred) || $occurred->gt(now()->addMinutes(5)) || $recorded->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['recorded_at' => ['Las horas deben ser coherentes y no estar en el futuro.']]);
        }

        return ['occurred_at' => $occurred->setTimezone(config('app.timezone'))->toDateTimeString(), 'recorded_at' => $recorded->setTimezone(config('app.timezone'))->toDateTimeString()];
    }

    private function touch(int $event): void
    {
        InventoryData::update('events', $event, []);
    }

    public function record(int $event, int $step, array $data, User $actor): array
    {
        return DB::transaction(function () use ($event, $step, $data, $actor) {
            $protocol = $this->context($event, $actor, 'mobile.record', $data['protocol_version']);
            $s = InventoryData::get('mobile_protocol_steps', $step, true);
            abort_unless($s->protocol_id === $protocol->id, 404);
            abort_unless($s->responsible_user_id === null || $s->responsible_user_id === $actor->id, 403);
            MobileContract::conflict(! DB::table('mobile_step_records')->where('step_id', $step)->exists() && ! DB::table('mobile_step_overrides')->where('step_id', $step)->exists(), 'El paso ya está registrado.', 'STEP_ALREADY_RECORDED');
            if (! in_array($data['capture_mode'], InventoryData::json($s->allowed_capture_modes), true)) {
                throw ValidationException::withMessages(['capture_mode' => ['Modo de captura no permitido.']]);
            }
            if ($s->requires_notes || app(MobileProtocolService::class)->milestone($s) !== null) {
                app(MobileProtocolService::class)->validateNotes($data['notes'] ?? null);
            }
            if (! $s->allow_notes && ! empty($data['notes'])) {
                throw ValidationException::withMessages(['notes' => ['El paso no admite notas.']]);
            }
            foreach (InventoryData::json($s->dependencies) as $dependency) {
                MobileContract::conflict(DB::table('mobile_step_records')->where('step_id', $dependency)->exists() || DB::table('mobile_step_overrides')->where('step_id', $dependency)->exists(), 'Registra primero los pasos anteriores.', 'STEP_DEPENDENCY_CONFLICT');
                $previous = DB::table('mobile_step_records')->where('step_id', $dependency)->first();
                MobileContract::conflict(! $previous || $previous->occurred_at <= \Carbon\CarbonImmutable::parse($data['occurred_at'])->setTimezone(config('app.timezone'))->toDateTimeString(), 'El hito es anterior a su dependencia.', 'STEP_ORDER_CONFLICT');
            }
            if (app(MobileProtocolService::class)->isReceiptReview($s)) {
                MobileContract::conflict(! DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->whereIn('status', ['draft', 'preparing', 'prepared', 'confirmed'])->exists(), 'Registra la recepción de tus despachos antes de este hito.', 'RECEIPT_PENDING');
            }
            $values = array_merge($data, $this->times($data), ['event_id' => $event, 'step_id' => $step, 'recorded_by' => $actor->id, 'original_payload' => $data, 'attachment_ids' => $data['attachment_ids'] ?? []]);
            $id = InventoryData::insert('mobile_step_records', $values);
            $files = $data['attachment_ids'] ?? [];
            if ($files && ! InventoryData::json($s->allowed_evidence)) {
                throw ValidationException::withMessages(['attachment_ids' => ['Este paso no admite evidencias.']]);
            }
            app(MobileAttachmentService::class)->link($files, $event, $actor->id, 'step_record', $id, InventoryData::json($s->allowed_evidence));
            app(AuditService::class)->record($actor->id, 'mobile_step_recorded', 'mobile_step_record', $id, $data);
            app(MobileProtocolService::class)->sync($protocol);
            $this->touch($event);

            return ['data' => app(TechnicianEventService::class)->record(InventoryData::get('mobile_step_records', $id), $actor)];
        });
    }

    public function correct(int $id, array $data, User $actor): array
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $record = InventoryData::get('mobile_step_records', $id);
            $this->context($record->event_id, $actor, 'mobile.correct_own');
            $record = InventoryData::get('mobile_step_records', $id, true);
            abort_unless($record->recorded_by === $actor->id, 403, 'Solo el autor puede corregir su registro.');
            MobileContract::conflict($record->revision === (int) $data['expected_revision'], 'El registro cambió.', 'RECORD_REVISION_CONFLICT', ['expected_revision' => ['Actualiza el registro.']]);
            $values = ['revision' => $record->revision + 1, 'corrected_by' => $actor->id, 'corrected_at' => now()];
            if (isset($data['occurred_at'])) {
                $values['occurred_at'] = $this->times(['occurred_at' => $data['occurred_at'], 'recorded_at' => MobileContract::utc($record->recorded_at)])['occurred_at'];
                $step = InventoryData::get('mobile_protocol_steps', $record->step_id);
                foreach (DB::table('mobile_step_records')->whereIn('step_id', InventoryData::json($step->dependencies))->get() as $previous) {
                    MobileContract::conflict($previous->occurred_at <= $values['occurred_at'], 'La corrección altera el orden de los hitos.', 'STEP_ORDER_CONFLICT');
                }
                foreach (DB::table('mobile_protocol_steps')->where('protocol_id', $step->protocol_id)->get() as $next) {
                    if (in_array($record->step_id, InventoryData::json($next->dependencies), true)) {
                        $nextRecord = DB::table('mobile_step_records')->where('step_id', $next->id)->first();
                        MobileContract::conflict(! $nextRecord || $nextRecord->occurred_at >= $values['occurred_at'], 'La corrección altera el orden de los hitos.', 'STEP_ORDER_CONFLICT');
                    }
                }
            }
            if (array_key_exists('notes', $data)) {
                $step = InventoryData::get('mobile_protocol_steps', $record->step_id);
                if (! $step->allow_notes && ! empty($data['notes'])) {
                    throw ValidationException::withMessages(['notes' => ['El paso no admite notas.']]);
                }
                if ($step->requires_notes || app(MobileProtocolService::class)->milestone($step) !== null) {
                    app(MobileProtocolService::class)->validateNotes($data['notes']);
                }
                $values['notes'] = $data['notes'];
            }
            InventoryData::update('mobile_step_records', $id, $values);
            InventoryData::insert('mobile_record_corrections', ['record_id' => $id, 'actor_id' => $actor->id, 'client_operation_id' => $data['client_operation_id'], 'revision' => $values['revision'], 'reason' => $data['correction_reason'], 'before' => (array) $record, 'after' => (array) InventoryData::get('mobile_step_records', $id), 'occurred_at' => now()]);
            app(AuditService::class)->record($actor->id, 'mobile_step_corrected', 'mobile_step_record', $id, $data, (array) $record);
            app(MobileProtocolService::class)->sync(app(TechnicianEventService::class)->snapshot($record->event_id));
            $this->touch($record->event_id);

            return ['data' => app(TechnicianEventService::class)->record(InventoryData::get('mobile_step_records', $id), $actor)];
        });
    }

    public function incident(int $event, array $data, User $actor): array
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            app(TechnicianEventService::class)->authorize($actor, $event, 'mobile.incident', true);
            if (! empty($data['dispatch_item_id'])) {
                $line = DB::table('event_dispatch_items')->join('event_dispatches', 'event_dispatches.id', '=', 'event_dispatch_items.event_dispatch_id')->where('event_dispatch_items.id', $data['dispatch_item_id'])->where('event_dispatches.event_id', $event)->where('technician_id', $actor->id)->whereIn('event_dispatches.status', ['confirmed', 'received', 'closed'])->exists();
                abort_unless($line, 403, 'La línea no pertenece a un despacho autorizado del evento.');
            }
            if (! empty($data['step_id'])) {
                $protocol = app(TechnicianEventService::class)->snapshot($event);
                abort_unless(DB::table('mobile_protocol_steps')->where('id', $data['step_id'])->where('protocol_id', $protocol->id)->exists(), 404);
            }
            $id = InventoryData::insert('mobile_incidents', array_merge($data, $this->times($data), ['event_id' => $event, 'created_by' => $actor->id, 'attachment_ids' => $data['attachment_ids'] ?? []]));
            app(MobileAttachmentService::class)->link($data['attachment_ids'] ?? [], $event, $actor->id, 'incident', $id);
            app(AuditService::class)->record($actor->id, 'mobile_incident_created', 'mobile_incident', $id, $data);
            app(DeparturePassService::class)->invalidate($event, $actor->id, 'Se reportó una incidencia sobre la operación.');
            $this->touch($event);

            return ['data' => MobileContract::dates((array) InventoryData::get('mobile_incidents', $id))];
        });
    }

    public function override(int $event, int $step, array $data, User $actor): array
    {
        return DB::transaction(function () use ($event, $step, $data, $actor) {
            abort_unless(app(TechnicianEventService::class)->canOverride($actor), 403, 'Se requiere autorización explícita de supervisor.');
            $p = $this->context($event, $actor, 'mobile.override', $data['protocol_version']);
            $s = InventoryData::get('mobile_protocol_steps', $step, true);
            abort_unless($s->protocol_id === $p->id, 404);
            MobileContract::conflict(! DB::table('mobile_step_records')->where('step_id', $step)->exists() && ! DB::table('mobile_step_overrides')->where('step_id', $step)->exists(), 'El paso ya está registrado.', 'STEP_ALREADY_RECORDED');
            $id = InventoryData::insert('mobile_step_overrides', ['event_id' => $event, 'step_id' => $step, 'protocol_version' => $p->version, 'actor_id' => $actor->id, 'client_operation_id' => $data['client_operation_id'], 'reason' => $data['reason']]);
            app(AuditService::class)->record($actor->id, 'mobile_step_overridden', 'mobile_step_override', $id, $data);
            app(MobileProtocolService::class)->sync($p);
            $this->touch($event);

            return ['data' => MobileContract::dates((array) InventoryData::get('mobile_step_overrides', $id))];
        });
    }

    public function close(int $event, array $data, User $actor): array
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $p = $this->context($event, $actor, 'mobile.close', $data['protocol_version']);
            app(MobileProtocolService::class)->validateNotes($data['notes'] ?? null);
            $times = $this->times($data);
            $latest = DB::table('mobile_step_records')->where('event_id', $event)->max('occurred_at');
            MobileContract::conflict(! $latest || $times['occurred_at'] >= $latest, 'La finalización es anterior a un hito registrado.', 'STEP_ORDER_CONFLICT');
            $progress = app(TechnicianEventService::class)->progress($p);
            MobileContract::conflict($progress['completed'] === $progress['required'], 'Faltan pasos obligatorios.', 'REQUIRED_STEPS_PENDING');
            MobileContract::conflict(! DB::table('mobile_incidents')->where('event_id', $event)->whereIn('severity', ['high', 'critical'])->where('status', '!=', 'resolved')->exists(), 'Hay incidencias críticas sin resolver.', 'CRITICAL_INCIDENTS_PENDING');
            MobileContract::conflict(! DB::table('event_dispatches')->where('event_id', $event)->whereIn('status', ['draft', 'preparing', 'prepared', 'confirmed'])->exists(), 'Hay despachos sin recepción.', 'RECEIPT_PENDING');
            MobileContract::conflict(! app(MobileMaterialsService::class)->hasPendingCustody($event), 'Concilia el material pendiente y las diferencias antes del cierre.', 'MATERIALS_PENDING');
            InventoryData::update('mobile_event_protocols', $p->id, ['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id, 'closing_notes' => trim($data['notes']), 'closure_occurred_at' => $times['occurred_at'], 'closure_recorded_at' => $times['recorded_at']]);
            $open = DB::table('event_dispatches')->where('event_id', $event)->whereNotIn('status', ['closed', 'cancelled'])->exists();
            app(MobileProtocolService::class)->sync(InventoryData::get('mobile_event_protocols', $p->id));
            InventoryData::update('events', $event, ['logistics_status' => $open ? 'awaiting_return' : 'closed']);
            app(AuditService::class)->record($actor->id, 'mobile_event_closed', 'event', $event, $data);

            return ['data' => ['event_id' => $event, 'status' => 'closed', 'operational_status' => app(MobileProtocolService::class)->state($p)['operational_status'], 'logistics_status' => $open ? 'awaiting_return' : 'closed', 'closed_at' => MobileContract::utc(now()), 'closed_by' => $actor->id]];
        });
    }
}
