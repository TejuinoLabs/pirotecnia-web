<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventDispatchService
{
    public function create(int $eventId, array $data, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $data, $actor) {
            $event = InventoryData::activeEvent($eventId);
            InventoryData::assert(in_array($event->logistics_status, ['ready', 'dispatched', 'in_operation'], true), 'La preparación debe estar lista.');
            $picking = InventoryData::get('event_pickings', $data['picking_id'], true);
            abort_unless($picking->event_id === $eventId, 404);
            InventoryData::assert($picking->status === 'completed' && ! DB::table('event_dispatches')->where('picking_id', $picking->id)->exists(), 'Picking incompleto o ya tiene salida.');
            $assigned = DB::table('employee_event')->join('employees', 'employees.id', '=', 'employee_id')->join('users', 'users.id', '=', 'employees.user_id')->where('event_id', $eventId)->where('employees.user_id', $data['technician_id'])->where('employees.status', 'active')->where('users.status', 'active')->exists();
            InventoryData::assert($assigned, 'El técnico debe estar asignado al evento.');
            $code = 'EVENT-'.$eventId;
            $destination = DB::table('inventory_locations')->where('code', $code)->first();
            $destinationId = $destination?->id ?? InventoryData::insert('inventory_locations', ['name' => 'Evento #'.$eventId, 'code' => $code, 'type' => 'event']);
            $id = InventoryData::insert('event_dispatches', ['event_id' => $eventId, 'picking_id' => $picking->id, 'warehouse_location_id' => $picking->warehouse_location_id, 'technician_id' => $data['technician_id'], 'destination_location_id' => $destinationId, 'notes' => $data['notes'] ?? null, 'version' => 1, 'status' => 'prepared']);
            foreach (DB::table('event_picking_items')->where('event_picking_id', $picking->id)->get() as $item) {
                $product = InventoryData::get('products', $item->product_id);
                InventoryData::insert('event_dispatch_items', ['event_dispatch_id' => $id, 'product_id' => $item->product_id, 'event_requirement_id' => $item->event_requirement_id, 'requirement_allocation_id' => $item->requirement_allocation_id, 'quantity' => $item->quantity_picked, 'is_returnable' => $product->is_returnable]);
            }
            app(AuditService::class)->record($actor, 'dispatch_created', 'event_dispatch', $id);
            app(\App\Services\Mobile\DeparturePassService::class)->invalidate($eventId, $actor, 'Se agregó un despacho al evento.');

            return $this->show($id);
        });
    }

    public function show(int $id): array
    {
        return ['dispatch' => (array) InventoryData::get('event_dispatches', $id), 'items' => DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->get()->toArray()];
    }

    public function identify(int $id, array $data, int $actor): array
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $original = InventoryData::get('event_dispatches', $id);
            InventoryData::activeEvent($original->event_id);
            $dispatch = InventoryData::get('event_dispatches', $id, true);
            \App\Services\Mobile\MobileContract::conflict(in_array($dispatch->status, ['draft', 'preparing', 'prepared'], true), 'Una entrega firmada no se edita. Registra una nueva entrega verificada.', 'DISPATCH_IMMUTABLE');
            \App\Services\Mobile\MobileContract::conflict(($data['expected_version'] ?? null) === $dispatch->version, 'La versión del despacho cambió.', 'DISPATCH_VERSION_CONFLICT');
            foreach ($data['items'] as $entry) {
                $line = InventoryData::get('event_dispatch_items', $entry['dispatch_item_id'], true);
                abort_unless($line->event_dispatch_id === $id, 404);
                $series = $entry['serial_numbers'];
                InventoryData::assert(count($series) * 1000 === \App\Services\Mobile\MobileContract::milli($line->quantity), 'Proporciona una serie por cada unidad del concepto.');
                foreach ($series as $index => $serial) {
                    InventoryData::assert(trim($serial) !== '', 'La serie no puede estar vacía.');
                    $values = ['product_id' => $line->product_id, 'event_requirement_id' => $line->event_requirement_id, 'requirement_allocation_id' => $line->requirement_allocation_id, 'is_returnable' => $line->is_returnable, 'quantity' => 1, 'serial_number' => $serial];
                    if ($index === 0) {
                        InventoryData::update('event_dispatch_items', $line->id, $values);
                    } else {
                        InventoryData::insert('event_dispatch_items', ['event_dispatch_id' => $id, ...$values]);
                    }
                }
            }
            $series = DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->whereNotNull('serial_number')->pluck('serial_number');
            InventoryData::assert($series->unique()->count() === $series->count(), 'No repitas series en el despacho.');
            InventoryData::update('event_dispatches', $id, ['version' => ($dispatch->version ?? 0) + 1]);
            app(\App\Services\Mobile\DeparturePassService::class)->invalidate($dispatch->event_id, $actor, 'Cambió la identificación de la carga.');
            app(AuditService::class)->record($actor, 'dispatch_identified', 'event_dispatch', $id, $data);

            return $this->show($id);
        });
    }

    public function confirm(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $original = InventoryData::get('event_dispatches', $id);
            $event = InventoryData::activeEvent($original->event_id);
            $dispatch = InventoryData::get('event_dispatches', $id, true);
            InventoryData::assert(in_array($dispatch->status, ['draft', 'prepared'], true), 'Salida ya confirmada.', 'DISPATCH_ALREADY_CONFIRMED');
            InventoryData::assert(in_array($event->logistics_status, ['ready', 'dispatched', 'in_operation'], true), 'Evento no está listo.');
            $picking = InventoryData::get('event_pickings', $dispatch->picking_id, true);
            InventoryData::assert($picking->status === 'completed', 'Picking incompleto.');
            $items = DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->orderBy('product_id')->get();
            $serials = $items->pluck('serial_number')->filter();
            InventoryData::assert($serials->unique()->count() === $serials->count(), 'No repitas series en un despacho.');
            foreach ($items as $item) {
                if ($item->serial_number) {
                    InventoryData::assert(\App\Services\Mobile\MobileContract::milli($item->quantity) === 1000, 'Cada serie debe identificar una línea de una sola unidad.');
                }
            }
            foreach ($items as $item) {
                $allocation = InventoryData::get('requirement_allocations', $item->requirement_allocation_id, true);
                InventoryData::assert($allocation->status === 'picked', 'Asignación no surtida.');
                app(InventoryMovementService::class)->record($item->product_id, (float) $item->quantity, $dispatch->warehouse_location_id, $dispatch->destination_location_id, 'event_dispatch', $actor, 'Salida al técnico #'.$dispatch->technician_id, 'event_dispatch', $id, 'Evento #'.$dispatch->event_id);
                // Adapter for old mobile checks; dispatch items remain the delivery source of truth.
                DB::table('productables')->where('productable_type', \App\Models\Event::class)->where('productable_id', $dispatch->event_id)->where('product_id', $item->product_id)->update(['check_almacen' => true]);
            }
            foreach ($items->pluck('requirement_allocation_id')->unique() as $allocationId) {
                InventoryData::update('requirement_allocations', $allocationId, ['status' => 'dispatched']);
            }
            foreach ($items->pluck('event_requirement_id')->unique() as $requirementId) {
                if (! DB::table('requirement_allocations')->where('event_requirement_id', $requirementId)->whereIn('status', ['reserved', 'picked'])->exists()) {
                    InventoryData::update('event_requirements', $requirementId, ['status' => 'dispatched']);
                }
            }
            InventoryData::update('event_dispatches', $id, ['status' => 'confirmed', 'dispatched_by' => $actor, 'dispatched_at' => now(), 'version' => $dispatch->version === null ? null : $dispatch->version + 1]);
            InventoryData::update('event_dispatches', $id, ['content_hash' => app(\App\Services\Mobile\DispatchIdentityService::class)->fingerprint($id)]);
            app(\App\Services\Mobile\DeparturePassService::class)->invalidate($dispatch->event_id, $actor, 'Se confirmó una nueva carga.');
            if ($event->logistics_status !== 'in_operation') {
                InventoryData::update('events', $dispatch->event_id, ['logistics_status' => 'dispatched']);
            }
            DB::table('event_preparations')->where('event_id', $dispatch->event_id)->update(['status' => 'dispatched', 'updated_at' => now()]);
            DB::table('inventory_reservations')->where('event_id', $dispatch->event_id)->update(['status' => 'fulfilled', 'updated_at' => now()]);
            app(AuditService::class)->record($actor, 'dispatch_confirmed', 'event_dispatch', $id);

            return $this->show($id);
        });
    }
}
