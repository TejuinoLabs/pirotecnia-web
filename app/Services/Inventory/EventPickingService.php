<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventPickingService
{
    public function create(int $eventId, int $location, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $location, $actor) {
            $event = InventoryData::activeEvent($eventId);
            InventoryData::assert($event->logistics_status === 'preparing', 'Inicia la preparación antes del picking.');
            $requirements = DB::table('event_requirements')->where('event_id', $eventId)->pluck('id');
            $allocations = DB::table('requirement_allocations')->whereIn('event_requirement_id', $requirements)->where('warehouse_location_id', $location)->where('status', 'reserved')->whereNotIn('id', DB::table('event_picking_items')->select('requirement_allocation_id'))->lockForUpdate()->get();
            InventoryData::assert($allocations->isNotEmpty(), 'No hay asignaciones pendientes para este almacén.');
            $id = InventoryData::insert('event_pickings', ['event_id' => $eventId, 'warehouse_location_id' => $location, 'started_by' => $actor, 'started_at' => now()]);
            foreach ($allocations as $allocation) {
                InventoryData::insert('event_picking_items', ['event_picking_id' => $id, 'event_requirement_id' => $allocation->event_requirement_id, 'requirement_allocation_id' => $allocation->id, 'product_id' => $allocation->product_id, 'quantity_required' => $allocation->quantity]);
            }
            app(AuditService::class)->record($actor, 'picking_created', 'event_picking', $id);

            return $this->show($eventId, $id);
        });
    }

    public function show(int $event, int $id): array
    {
        $picking = InventoryData::get('event_pickings', $id);
        abort_unless($picking->event_id === $event, 404);

        return ['picking' => (array) $picking, 'items' => DB::table('event_picking_items')->where('event_picking_id', $id)->get()->toArray()];
    }

    public function confirm(int $eventId, int $id, int $itemId, float $quantity, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $id, $itemId, $quantity, $actor) {
            InventoryData::activeEvent($eventId);
            $picking = InventoryData::get('event_pickings', $id, true);
            abort_unless($picking->event_id === $eventId, 404);
            InventoryData::assert($picking->status === 'in_progress', 'Picking cerrado.');
            $item = InventoryData::get('event_picking_items', $itemId, true);
            abort_unless($item->event_picking_id === $id, 404);
            InventoryData::assert($quantity === (float) $item->quantity_required, 'Confirma la cantidad completa asignada.');
            InventoryData::update('event_picking_items', $itemId, ['quantity_picked' => $quantity, 'status' => 'confirmed']);
            InventoryData::update('requirement_allocations', $item->requirement_allocation_id, ['status' => 'picked']);
            app(AuditService::class)->record($actor, 'picking_item_confirmed', 'event_picking_item', $itemId, ['quantity' => $quantity]);

            return $this->show($eventId, $id);
        });
    }

    public function complete(int $eventId, int $id, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $id, $actor) {
            InventoryData::activeEvent($eventId);
            $picking = InventoryData::get('event_pickings', $id, true);
            abort_unless($picking->event_id === $eventId, 404);
            InventoryData::assert($picking->status === 'in_progress' && ! DB::table('event_picking_items')->where('event_picking_id', $id)->where('status', '!=', 'confirmed')->exists(), 'Picking incompleto o cerrado.');
            InventoryData::update('event_pickings', $id, ['status' => 'completed', 'completed_by' => $actor, 'completed_at' => now()]);
            app(AuditService::class)->record($actor, 'picking_completed', 'event_picking', $id);

            return $this->show($eventId, $id);
        });
    }
}
