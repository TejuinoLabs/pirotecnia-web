<?php

namespace App\Services\Mobile;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileReturnService
{
    public function reconciliation(int $dispatch): array
    {
        $d = InventoryData::get('event_dispatches', $dispatch);
        $receipt = DB::table('technician_receipts')->where('event_dispatch_id', $dispatch)->first();
        $items = DB::table('event_dispatch_items')->where('event_dispatch_id', $dispatch)->orderBy('id')->get()->map(function ($item) use ($receipt) {
            $received = $receipt ? DB::table('technician_receipt_items')->where('technician_receipt_id', $receipt->id)->where('dispatch_item_id', $item->id)->value('received_quantity') : '0';
            $totals = ['consumed' => 0, 'returned' => 0, 'damaged' => 0, 'lost' => 0];
            foreach (DB::table('mobile_return_declaration_items')->where('dispatch_item_id', $item->id)->get() as $declared) {
                foreach ($totals as $key => $total) {
                    $totals[$key] += MobileContract::milli($declared->{'quantity_'.$key});
                }
            }

            return ['dispatch_item_id' => $item->id, 'product_id' => $item->product_id, 'is_returnable' => (bool) $item->is_returnable, 'quantity_dispatched' => MobileContract::quantity(MobileContract::milli($item->quantity)), 'quantity_received' => MobileContract::quantity(MobileContract::milli($received)), 'quantity_pending' => MobileContract::quantity(max(0, MobileContract::milli($received) - array_sum($totals))), ...collect($totals)->mapWithKeys(fn ($total, $key) => ['quantity_'.$key => MobileContract::quantity($total)])->all()];
        })->all();

        return ['dispatch_id' => $dispatch, 'revision' => $d->mobile_return_revision, 'items' => $items];
    }

    public function declare(int $event, array $data, User $actor): array
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            app(TechnicianEventService::class)->authorize($actor, $event, 'mobile.return', true);
            $d = InventoryData::get('event_dispatches', $data['dispatch_id'], true);
            abort_unless($d->event_id === $event && $d->technician_id === $actor->id, 403);
            MobileContract::conflict($d->status === 'received' && DB::table('technician_receipts')->where('event_dispatch_id', $d->id)->exists(), 'Registra la recepción antes de declarar una devolución.', 'RETURN_RECEIPT_REQUIRED');
            MobileContract::conflict($d->mobile_return_revision === (int) $data['expected_revision'], 'La conciliación cambió.', 'RETURN_REVISION_CONFLICT');
            MobileContract::conflict(! DB::table('event_returns')->where('event_dispatch_id', $d->id)->exists(), 'El almacén ya inició la conciliación física.', 'WAREHOUSE_RETURN_IN_PROGRESS');
            $available = collect($this->reconciliation($d->id)['items'])->keyBy('dispatch_item_id');
            $lines = [];
            foreach ($data['items'] as $item) {
                abort_unless($available->has($item['dispatch_item_id']), 403, 'La línea no pertenece al despacho.');
                $original = $available[$item['dispatch_item_id']];
                $total = 0;
                $product = Product::findOrFail($original['product_id']);
                foreach (['consumed', 'returned', 'damaged', 'lost'] as $field) {
                    $milli = MobileContract::milli($item['quantity_'.$field]);
                    if (app(MobileDispatchService::class)->precision($product) === 0 && $milli % 1000 !== 0) {
                        throw ValidationException::withMessages(['items' => ['El artículo requiere cantidades enteras.']]);
                    }
                    $total += $milli;
                }
                if ($total === 0) {
                    throw ValidationException::withMessages(['items' => ['Cada línea debe declarar una cantidad mayor que cero.']]);
                }
                MobileContract::conflict($total <= MobileContract::milli($original['quantity_pending']), 'La declaración excede lo recibido pendiente.', 'RETURN_QUANTITY_CONFLICT');
                if ($original['is_returnable'] && MobileContract::milli($item['quantity_consumed']) > 0) {
                    throw ValidationException::withMessages(['items' => ['El equipo retornable no puede declararse consumido.']]);
                }
                $lines[] = $item + ['quantity_pending' => MobileContract::quantity(MobileContract::milli($original['quantity_pending']) - $total)];
            }
            $id = InventoryData::insert('mobile_return_declarations', array_merge(collect($data)->except(['items', 'expected_revision'])->all(), app(MobileOperationService::class)->times($data), ['event_id' => $event, 'created_by' => $actor->id, 'revision' => $d->mobile_return_revision + 1]));
            foreach ($lines as $line) {
                InventoryData::insert('mobile_return_declaration_items', ['declaration_id' => $id, ...$line]);
            }
            InventoryData::update('event_dispatches', $d->id, ['mobile_return_revision' => $d->mobile_return_revision + 1]);
            InventoryData::update('events', $event, ['logistics_status' => 'awaiting_return']);
            app(AuditService::class)->record($actor->id, 'mobile_return_declared', 'mobile_return_declaration', $id, $data);

            return ['data' => $this->show($id)];
        });
    }

    public function show(int $id): array
    {
        return MobileContract::dates((array) InventoryData::get('mobile_return_declarations', $id)) + ['items' => DB::table('mobile_return_declaration_items')->where('declaration_id', $id)->orderBy('id')->get()->map(fn ($i) => MobileContract::dates((array) $i))->all()];
    }

    public function list(int $event, User $actor): array
    {
        app(TechnicianEventService::class)->authorize($actor, $event);
        $dispatches = DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->whereIn('status', ['confirmed', 'received', 'closed'])->orderBy('id')->pluck('id');

        return MobileContract::page(DB::table('mobile_return_declarations')->whereIn('dispatch_id', $dispatches)->orderBy('id')->paginate(20), fn ($row) => $this->show($row->id)) + ['reconciliations' => $dispatches->map(fn ($id) => $this->reconciliation($id))->all()];
    }
}
