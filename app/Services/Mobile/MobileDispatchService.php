<?php

namespace App\Services\Mobile;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;

class MobileDispatchService
{
    public function precision(object $product): int
    {
        return $product->is_returnable || in_array(mb_strtolower($product->unit), ['pz', 'pza', 'pzas', 'pieza', 'piezas', 'unidad', 'unidades', 'unit', 'pcs'], true) ? 0 : 3;
    }

    public function owned(int $id, User $actor): object
    {
        $d = InventoryData::get('event_dispatches', $id);
        app(TechnicianEventService::class)->authorize($actor, $d->event_id);
        abort_unless($d->technician_id === $actor->id && in_array($d->status, ['confirmed', 'received', 'closed'], true), 403);

        return $d;
    }

    public function show(int $id, User $actor): array
    {
        $d = $this->owned($id, $actor);
        $items = DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->orderBy('id')->get()->map(function ($line) {
            $p = Product::findOrFail($line->product_id);

            return MobileContract::dates((array) $line) + ['name' => $p->display_name, 'sku' => $p->sku, 'unit' => $p->unit, 'category' => $line->is_returnable ? 'returnable_equipment' : (in_array((int) $p->product_role_id, config('mobile.pyrotechnic_product_role_ids'), true) ? 'pyrotechnic' : 'consumable'), 'quantity_precision' => $this->precision($p), 'serial_numbers' => $line->serial_number ? [$line->serial_number] : []];
        })->all();
        foreach ($items as &$item) {
            $item['is_returnable'] = (bool) $item['is_returnable'];
        }
        $receipt = DB::table('technician_receipts')->where('event_dispatch_id', $id)->first();
        $r = $receipt ? MobileContract::dates((array) $receipt) + ['received_by' => $receipt->technician_id, 'items' => DB::table('technician_receipt_items')->where('technician_receipt_id', $receipt->id)->orderBy('id')->get()->map(fn ($i) => MobileContract::dates((array) $i))->all()] : null;

        return ['dispatch' => MobileContract::dates((array) $d), 'items' => $items, 'receipt' => $r, 'incident_ids' => $receipt ? DB::table('mobile_incidents')->where('receipt_id', $receipt->id)->orderBy('id')->pluck('id')->all() : [], 'return_revision' => $d->mobile_return_revision];
    }

    public function forEvent(int $event, User $actor): array
    {
        app(TechnicianEventService::class)->authorize($actor, $event);
        $ids = DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->whereIn('status', ['confirmed', 'received', 'closed'])->orderBy('id')->pluck('id');
        abort_if($ids->isEmpty(), 404);

        return ['dispatches' => $ids->map(fn ($id) => $this->show($id, $actor))->all()];
    }
}
