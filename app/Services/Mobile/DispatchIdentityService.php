<?php

namespace App\Services\Mobile;

use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchIdentityService
{
    public function fingerprint(int $dispatch): string
    {
        $row = InventoryData::get('event_dispatches', $dispatch);
        $items = DB::table('event_dispatch_items')->where('event_dispatch_id', $dispatch)->orderBy('id')->get()->map(fn ($i) => [
            'id' => $i->id, 'product_id' => $i->product_id, 'quantity' => MobileContract::quantity(MobileContract::milli($i->quantity)),
            'serial_number' => $i->serial_number, 'is_returnable' => (bool) $i->is_returnable,
        ])->all();

        return hash('sha256', json_encode([$row->event_id, $row->technician_id, $row->dispatched_by, $items], JSON_THROW_ON_ERROR));
    }

    public function matches(object $dispatch): bool
    {
        return $dispatch->content_hash === null || hash_equals($dispatch->content_hash, $this->fingerprint($dispatch->id));
    }

    public function validateSerial(object $line, ?string $serial, int $quantity): void
    {
        if ($line->serial_number !== null) {
            if (MobileContract::milli($line->quantity) !== 1000 || ($quantity > 0 && $serial !== $line->serial_number) || ($quantity === 0 && $serial !== null && $serial !== $line->serial_number)) {
                throw ValidationException::withMessages(['items' => 'La serie debe coincidir con la unidad identificada del despacho.']);
            }
        } elseif ($serial !== null && $serial !== '') {
            throw ValidationException::withMessages(['items' => 'Esta serie no pertenece a la línea del despacho.']);
        }
    }
}
