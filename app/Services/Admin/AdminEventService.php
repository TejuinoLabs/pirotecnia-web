<?php

namespace App\Services\Admin;

use App\Models\Event;
use App\Models\Package;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\EventRequirementService;
use App\Services\Inventory\InventoryData;
use App\Services\Mobile\DeparturePassService;
use App\Services\Mobile\MobileContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminEventService
{
    public function show(int $id): array
    {
        $model = Event::with(['packages', 'employees'])->findOrFail($id);
        $data = $model->toArray();
        $data['employees'] = $model->employees->map(fn ($row) => app(AdminEmployeeService::class)->show($row->id))->all();

        return $data;
    }

    private function assertEditable(int $event): void
    {
        MobileContract::conflict(! DB::table('event_dispatches')->where('event_id', $event)->exists()
            && ! DB::table('event_pickings')->where('event_id', $event)->exists()
            && ! DB::table('mobile_step_records')->where('event_id', $event)->exists()
            && ! DB::table('mobile_step_overrides')->where('event_id', $event)->exists()
            && ! DB::table('requirement_allocations')->whereIn('event_requirement_id', DB::table('event_requirements')->where('event_id', $event)->select('id'))->exists(),
            'El evento tiene asignaciones, preparación o evidencia operativa; no se puede cambiar su contrato.', 'EVENT_OPERATIONAL_CONFLICT');
    }

    public function update(int $id, array $payload, int $actor): array
    {
        $data = Validator::make($payload, ['client_name' => ['sometimes', 'string', 'max:255'], 'phone' => ['sometimes', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:2000'], 'event_address' => ['sometimes', 'string', 'max:2000'], 'event_date' => ['sometimes', 'date'],
            'event_type_id' => ['nullable', 'integer', 'exists:event_types,id'], 'package_id' => ['sometimes', 'integer', 'exists:packages,id'], 'package_quantity' => ['sometimes', 'integer', 'min:1'], 'notes' => ['nullable', 'string', 'max:10000']])->validate();
        if (isset($data['event_date'])) {
            $data['event_date'] = \Carbon\CarbonImmutable::parse($data['event_date'], config('mobile.event_timezone'))->setTimezone(config('mobile.event_timezone'))->toDateTimeString();
        }

        return DB::transaction(function () use ($id, $data, $actor) {
            $event = Event::whereKey($id)->lockForUpdate()->firstOrFail();
            MobileContract::conflict(! in_array($event->status, ['closed', 'cancelled'], true), 'Reabre el evento antes de editarlo.', 'EVENT_STATE_CONFLICT');
            $contractChanged = collect($data)->only(['event_date', 'event_address', 'event_type_id', 'package_id'])->contains(fn ($value, $key) => (string) $value !== (string) $event->$key)
                || (isset($data['package_quantity']) && $data['package_quantity'] !== (int) ($event->packages()->where('packages.id', $event->package_id)->first()?->pivot->quantity ?? 1));
            $old = $this->show($id);
            if ($contractChanged) {
                $this->assertEditable($id);
            }
            if (isset($data['event_date'])) {
                $assigned = $event->employees()->pluck('employees.id');
                MobileContract::conflict(! DB::table('employee_event')->join('events', 'events.id', '=', 'event_id')->whereIn('employee_id', $assigned)->where('event_id', '!=', $id)->where('event_date', $data['event_date'])->whereNotIn('events.status', ['closed', 'cancelled'])->exists(), 'El nuevo horario coincide con otro evento del personal asignado.', 'EMPLOYEE_UNAVAILABLE');
            }
            $fields = collect($data)->except('package_quantity')->all();
            $event->fill($fields)->save();
            if ($contractChanged && (isset($data['package_id']) || isset($data['package_quantity']))) {
                $package = Package::whereKey($event->package_id)->where('status', '!=', 'inactive')->first();
                abort_unless($package, 422, 'Selecciona un paquete activo.');
                $quantity = $data['package_quantity'] ?? (int) ($event->packages()->where('packages.id', $package->id)->first()?->pivot->quantity ?? 1);
                $event->packages()->sync([$package->id => ['quantity' => $quantity, 'price' => $package->price]]);
                $event->products()->detach();
                $event->equipments()->detach();
                foreach ($package->materials as $item) {
                    $event->products()->attach($item->id, ['quantity' => $item->pivot->quantity * $quantity, 'price' => $item->pivot->price]);
                }
                foreach ($package->equipments as $item) {
                    $event->equipments()->attach($item->id, ['quantity' => $item->pivot->quantity * $quantity]);
                }
                $ids = DB::table('event_requirements')->where('event_id', $id)->pluck('id');
                DB::table('requirement_constraints')->where('owner_type', 'event_requirement')->whereIn('owner_id', $ids)->delete();
                DB::table('inventory_reservations')->where('event_id', $id)->delete();
                DB::table('inventory_alerts')->where('event_id', $id)->delete();
                DB::table('event_requirements')->where('event_id', $id)->delete();
                app(EventRequirementService::class)->snapshot($event, $actor);
            }
            if ($contractChanged) {
                $protocol = DB::table('mobile_event_protocols')->where('event_id', $id)->first();
                if ($protocol) {
                    InventoryData::update('mobile_event_protocols', $protocol->id, ['version' => $protocol->version + 1, 'package_snapshot' => ['packages' => $event->packages()->get(['packages.id', 'name', 'description'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description])->all()]]);
                }
                app(DeparturePassService::class)->invalidate($id, $actor, 'Cambió el contrato del evento.');
            }
            app(AuditService::class)->record($actor, 'admin_event_updated', 'event', $id, $data, $old);

            return $this->show($id);
        });
    }

    public function employees(int $id, array $payload, int $actor): array
    {
        $data = Validator::make($payload, ['employee_ids' => ['present', 'array'], 'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id']])->validate();

        return DB::transaction(function () use ($id, $data, $actor) {
            $event = Event::whereKey($id)->lockForUpdate()->firstOrFail();
            MobileContract::conflict(! in_array($event->status, ['closed', 'cancelled'], true), 'El evento no admite asignaciones.', 'EVENT_STATE_CONFLICT');
            $selected = DB::table('employees')->whereIn('id', $data['employee_ids'])->orderBy('id')->lockForUpdate()->get();
            foreach ($selected as $employee) {
                abort_unless($employee->status === 'active', 422, 'Solo se asigna personal activo.');
                abort_if($employee->user_id && ! DB::table('users')->where('id', $employee->user_id)->where('status', 'active')->exists(), 422, 'La cuenta del empleado está desactivada.');
                $busy = DB::table('employee_event')->join('events', 'events.id', '=', 'event_id')->where('employee_id', $employee->id)->where('event_id', '!=', $id)->where('event_date', $event->event_date)->whereNotIn('events.status', ['cancelled', 'closed'])->exists();
                MobileContract::conflict(! $busy, 'El empleado #'.$employee->id.' ya tiene un evento a esa hora.', 'EMPLOYEE_UNAVAILABLE');
            }
            $removed = $event->employees()->whereNotIn('employees.id', $data['employee_ids'])->get();
            foreach ($removed as $employee) {
                MobileContract::conflict(! DB::table('event_dispatches')->where('event_id', $id)->where('technician_id', $employee->user_id)->whereNotIn('status', ['closed', 'cancelled'])->exists(), 'No retires al responsable de una entrega sin conciliar.', 'EMPLOYEE_HAS_CUSTODY');
            }
            $old = $event->employees()->pluck('employees.id')->all();
            $event->employees()->sync($data['employee_ids']);
            $event->touch();
            app(DeparturePassService::class)->invalidate($id, $actor, 'Cambió el personal asignado.');
            app(AuditService::class)->record($actor, 'admin_event_employees_changed', 'event', $id, $data, ['employee_ids' => $old]);

            return $this->show($id);
        });
    }

    public function transition(int $id, string $action, string $reason, int $actor): array
    {
        return DB::transaction(function () use ($id, $action, $reason, $actor) {
            $event = InventoryData::get('events', $id, true);
            $allowed = ['confirm' => ['pending'], 'close' => ['confirmed'], 'reopen' => ['closed', 'cancelled'], 'cancel' => ['pending', 'confirmed']];
            MobileContract::conflict(isset($allowed[$action]) && in_array($event->status, $allowed[$action], true), 'Transición de evento no permitida.', 'EVENT_STATE_CONFLICT');
            if ($action === 'close') {
                MobileContract::conflict(! DB::table('event_dispatches')->where('event_id', $id)->whereNotIn('status', ['closed', 'cancelled'])->exists()
                    && ! DB::table('event_returns')->where('event_id', $id)->where('status', '!=', 'confirmed')->exists()
                    && ! DB::table('mobile_incidents')->where('event_id', $id)->where('status', '!=', 'resolved')->exists()
                    && ! DB::table('mobile_event_protocols')->where('event_id', $id)->where('status', '!=', 'closed')->exists()
                    && ! DB::table('event_requirements')->where('event_id', $id)->whereNotIn('status', ['closed', 'cancelled'])->exists()
                    && ! DB::table('requirement_allocations')->whereIn('event_requirement_id', DB::table('event_requirements')->where('event_id', $id)->select('id'))->whereIn('status', ['reserved', 'picked', 'dispatched'])->exists(),
                    'Falta conciliar materiales, incidencias o bitácora operativa.', 'EVENT_CLOSURE_BLOCKED');
            }
            if ($action === 'cancel') {
                MobileContract::conflict(! DB::table('event_dispatches')->where('event_id', $id)->whereIn('status', ['confirmed', 'received'])->exists(), 'Concilia las entregas antes de cancelar.', 'EVENT_HAS_DISPATCHES');
                DB::table('requirement_allocations')->whereIn('event_requirement_id', DB::table('event_requirements')->where('event_id', $id)->select('id'))->whereIn('status', ['reserved', 'picked'])->update(['status' => 'cancelled', 'updated_at' => now()]);
                DB::table('inventory_reservations')->where('event_id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                DB::table('event_dispatches')->where('event_id', $id)->whereIn('status', ['draft', 'preparing', 'prepared'])->update(['status' => 'cancelled', 'updated_at' => now()]);
                DB::table('event_pickings')->where('event_id', $id)->where('status', 'in_progress')->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            if ($action === 'reopen') {
                $protocol = DB::table('mobile_event_protocols')->where('event_id', $id)->first();
                if ($protocol) {
                    InventoryData::update('mobile_event_protocols', $protocol->id, ['version' => $protocol->version + 1]);
                }
                if ($event->status === 'cancelled') {
                    DB::table('event_requirements')->where('event_id', $id)->whereNotIn('status', ['dispatched', 'closed'])->update(['quantity_allocated' => 0, 'status' => 'pending', 'updated_at' => now()]);
                    DB::table('inventory_reservations')->where('event_id', $id)->update(['status' => 'active', 'updated_at' => now()]);
                }
            }
            $status = match ($action) {
                'confirm', 'reopen' => 'confirmed', 'close' => 'closed', default => 'cancelled'
            };
            InventoryData::update('events', $id, ['status' => $status] + ($action === 'reopen' && $event->status === 'cancelled' ? ['logistics_status' => 'pending'] : []));
            app(DeparturePassService::class)->invalidate($id, $actor, 'Estado administrativo actualizado: '.$reason);
            app(AuditService::class)->record($actor, 'admin_event_'.$action, 'event', $id, ['reason' => $reason, 'status' => $status], ['status' => $event->status]);

            return $this->show($id);
        });
    }
}
