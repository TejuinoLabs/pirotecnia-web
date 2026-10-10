<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventDispatchService
{
    public function create(int $eventId, array $data, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $data, $actor) {
            $event = InventoryData::get('events', $eventId, true);
            InventoryData::assert(in_array($event->logistics_status, ['ready', 'dispatched', 'in_operation'], true), 'La preparación debe estar lista.');
            $picking = InventoryData::get('event_pickings', $data['picking_id'], true);
            abort_unless($picking->event_id === $eventId, 404);
            InventoryData::assert($picking->status === 'completed' && ! DB::table('event_dispatches')->where('picking_id', $picking->id)->exists(), 'Picking incompleto o ya tiene salida.');
            $assigned = DB::table('employee_event')->join('employees', 'employees.id', '=', 'employee_id')->where('event_id', $eventId)->where('employees.user_id', $data['technician_id'])->exists();
            InventoryData::assert($assigned, 'El técnico debe estar asignado al evento.');
            $code = 'EVENT-'.$eventId;
            $destination = DB::table('inventory_locations')->where('code', $code)->first();
            $destinationId = $destination?->id ?? InventoryData::insert('inventory_locations', ['name' => 'Evento #'.$eventId, 'code' => $code, 'type' => 'event']);
            $id = InventoryData::insert('event_dispatches', ['event_id' => $eventId, 'picking_id' => $picking->id, 'warehouse_location_id' => $picking->warehouse_location_id, 'technician_id' => $data['technician_id'], 'destination_location_id' => $destinationId, 'notes' => $data['notes'] ?? null]);
            foreach (DB::table('event_picking_items')->where('event_picking_id', $picking->id)->get() as $item) {
                $product = InventoryData::get('products', $item->product_id);
                InventoryData::insert('event_dispatch_items', ['event_dispatch_id' => $id, 'product_id' => $item->product_id, 'event_requirement_id' => $item->event_requirement_id, 'requirement_allocation_id' => $item->requirement_allocation_id, 'quantity' => $item->quantity_picked, 'is_returnable' => $product->is_returnable]);
            }
            app(AuditService::class)->record($actor, 'dispatch_created', 'event_dispatch', $id);

            return $this->show($id);
        });
    }

    public function show(int $id): array
    {
        return ['dispatch' => (array) InventoryData::get('event_dispatches', $id), 'items' => DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->get()->toArray()];
    }

    public function confirm(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $original = InventoryData::get('event_dispatches', $id);
            $event = InventoryData::get('events', $original->event_id, true);
            $dispatch = InventoryData::get('event_dispatches', $id, true);
            InventoryData::assert($dispatch->status === 'draft', 'Salida ya confirmada.', 'DISPATCH_ALREADY_CONFIRMED');
            InventoryData::assert(in_array($event->logistics_status, ['ready', 'dispatched', 'in_operation'], true), 'Evento no está listo.');
            $picking = InventoryData::get('event_pickings', $dispatch->picking_id, true);
            InventoryData::assert($picking->status === 'completed', 'Picking incompleto.');
            $items = DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->orderBy('product_id')->get();
            foreach ($items as $item) {
                $allocation = InventoryData::get('requirement_allocations', $item->requirement_allocation_id, true);
                InventoryData::assert($allocation->status === 'picked', 'Asignación no surtida.');
                app(InventoryMovementService::class)->record($item->product_id, (float) $item->quantity, $dispatch->warehouse_location_id, $dispatch->destination_location_id, 'event_dispatch', $actor, 'Salida al técnico #'.$dispatch->technician_id, 'event_dispatch', $id, 'Evento #'.$dispatch->event_id);
                InventoryData::update('requirement_allocations', $allocation->id, ['status' => 'dispatched']);
                $pending = DB::table('requirement_allocations')->where('event_requirement_id', $item->event_requirement_id)->whereIn('status', ['reserved', 'picked'])->exists();
                if (! $pending) {
                    InventoryData::update('event_requirements', $item->event_requirement_id, ['status' => 'dispatched']);
                }
                // Adapter for old mobile checks; dispatch items remain the delivery source of truth.
                DB::table('productables')->where('productable_type', \App\Models\Event::class)->where('productable_id', $dispatch->event_id)->where('product_id', $item->product_id)->update(['check_almacen' => true]);
            }
            InventoryData::update('event_dispatches', $id, ['status' => 'confirmed', 'dispatched_by' => $actor, 'dispatched_at' => now()]);
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
