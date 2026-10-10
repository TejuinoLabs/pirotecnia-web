<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventReturnService
{
    public function create(int $eventId, array $data, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $data, $actor) {
            InventoryData::get('events', $eventId, true);
            $dispatch = InventoryData::get('event_dispatches', $data['event_dispatch_id'], true);
            abort_unless($dispatch->event_id === $eventId, 404);
            InventoryData::assert(in_array($dispatch->status, ['confirmed', 'received'], true) && ! DB::table('event_returns')->where('event_dispatch_id', $dispatch->id)->exists(), 'Salida no admite nueva devolución.');
            $warehouse = InventoryData::get('inventory_locations', $data['warehouse_location_id']);
            InventoryData::assert($warehouse->active && $warehouse->type === 'warehouse', 'Almacén inválido.');
            $expected = DB::table('event_dispatch_items')->where('event_dispatch_id', $dispatch->id)->get()->keyBy('id');
            $ids = collect($data['items'])->pluck('dispatch_item_id');
            InventoryData::assert($ids->count() === $expected->count() && $ids->unique()->count() === $expected->count() && $ids->diff($expected->keys())->isEmpty(), 'Concilia todas las líneas despachadas una vez.');
            $id = InventoryData::insert('event_returns', ['event_id' => $eventId, 'event_dispatch_id' => $dispatch->id, 'warehouse_location_id' => $warehouse->id, 'damaged_location_id' => $data['damaged_location_id'] ?? null, 'returned_by' => $data['returned_by'] ?? $dispatch->technician_id, 'notes' => $data['notes'] ?? null]);
            foreach ($data['items'] as $entry) {
                $line = $expected[$entry['dispatch_item_id']];
                $total = 0;
                foreach (['consumed', 'returned', 'damaged', 'lost'] as $field) {
                    InventoryData::assert(($entry['quantity_'.$field] ?? 0) >= 0, 'Cantidad negativa.');
                    $total += (float) ($entry['quantity_'.$field] ?? 0);
                }
                InventoryData::assert(abs($total - (float) $line->quantity) < 0.0005, 'Las cantidades deben sumar lo despachado.');
                InventoryData::assert(! $line->is_returnable || (float) ($entry['quantity_consumed'] ?? 0) === 0.0, 'El equipo retornable no puede consumirse.');
                InventoryData::insert('event_return_items', ['event_return_id' => $id, 'product_id' => $line->product_id, 'quantity_dispatched' => $line->quantity, ...$entry]);
            }
            InventoryData::update('events', $eventId, ['logistics_status' => 'awaiting_return']);
            app(AuditService::class)->record($actor, 'return_created', 'event_return', $id, $data);

            return $this->show($id);
        });
    }

    public function show(int $id): array
    {
        return ['return' => (array) InventoryData::get('event_returns', $id), 'items' => DB::table('event_return_items')->where('event_return_id', $id)->get()->toArray()];
    }

    public function confirm(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $original = InventoryData::get('event_returns', $id);
            InventoryData::get('events', $original->event_id, true);
            $return = InventoryData::get('event_returns', $id, true);
            InventoryData::assert($return->status === 'draft', 'Devolución ya confirmada.', 'RETURN_ALREADY_CONFIRMED');
            $dispatch = InventoryData::get('event_dispatches', $return->event_dispatch_id, true);
            foreach (DB::table('event_return_items')->where('event_return_id', $id)->orderBy('product_id')->get() as $item) {
                if ((float) $item->quantity_damaged > 0) {
                    InventoryData::assert($return->damaged_location_id !== null, 'Selecciona ubicación de daños.');
                    $damaged = InventoryData::get('inventory_locations', $return->damaged_location_id);
                    InventoryData::assert($damaged->type === 'damaged' && $damaged->active, 'Ubicación de daños inválida.');
                }
                foreach (['returned' => ['event_return', $return->warehouse_location_id], 'damaged' => ['damage', $return->damaged_location_id], 'lost' => ['loss', null], 'consumed' => ['consumption', null]] as $field => [$type, $destination]) {
                    $quantity = (float) $item->{'quantity_'.$field};
                    if ($quantity > 0) {
                        app(InventoryMovementService::class)->record($item->product_id, $quantity, $dispatch->destination_location_id, $destination, $type, $actor, $return->notes ?: 'Conciliación del evento', 'event_return', $id, 'Evento #'.$return->event_id);
                    }
                }
                $dispatchItem = InventoryData::get('event_dispatch_items', $item->dispatch_item_id);
                InventoryData::update('requirement_allocations', $dispatchItem->requirement_allocation_id, ['status' => (float) $item->quantity_returned > 0 ? 'returned' : 'consumed']);
            }
            InventoryData::update('event_returns', $id, ['status' => 'confirmed', 'received_by' => $actor, 'returned_at' => now()]);
            InventoryData::update('event_dispatches', $dispatch->id, ['status' => 'closed']);
            DB::table('mobile_return_declarations')->where('dispatch_id', $dispatch->id)->where('status', 'declared')->update(['status' => 'warehouse_confirmed', 'warehouse_confirmed_at' => now(), 'warehouse_return_id' => $id, 'updated_at' => now()]);
            $open = DB::table('event_dispatches')->where('event_id', $return->event_id)->where('status', '!=', 'closed')->exists();
            $open = $open || DB::table('requirement_allocations')->whereIn('event_requirement_id', DB::table('event_requirements')->where('event_id', $return->event_id)->select('id'))->whereIn('status', ['reserved', 'picked'])->exists();
            if (! $open) {
                InventoryData::update('events', $return->event_id, ['logistics_status' => 'closed']);
                DB::table('event_requirements')->where('event_id', $return->event_id)->update(['status' => 'closed', 'updated_at' => now()]);
                DB::table('event_preparations')->where('event_id', $return->event_id)->update(['status' => 'closed', 'updated_at' => now()]);
            }
            app(AuditService::class)->record($actor, 'return_confirmed', 'event_return', $id);

            return $this->show($id);
        });
    }
}
