<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    public const TYPES = ['purchase_receipt', 'event_dispatch', 'event_return', 'sale', 'transfer', 'adjustment_in', 'adjustment_out', 'damage', 'loss', 'correction', 'stand_transfer', 'stand_return', 'consumption'];

    public function record(int $productId, float $quantity, ?int $from, ?int $to, string $type, int $actor, string $notes, ?string $source = null, ?int $sourceId = null, ?string $reference = null): int
    {
        InventoryData::assert($quantity > 0 && round($quantity, 3) === $quantity, 'Cantidad inválida; máximo tres decimales.');
        InventoryData::assert(in_array($type, self::TYPES, true) && trim($notes) !== '' && ($from || $to) && $from !== $to, 'Movimiento inválido.');

        return DB::transaction(function () use ($productId, $quantity, $from, $to, $type, $actor, $notes, $source, $sourceId, $reference) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            InventoryData::assert((bool) $product->tracks_inventory, 'El artículo no controla inventario.');
            foreach (array_filter([$from, $to]) as $locationId) {
                $location = InventoryData::get('inventory_locations', $locationId);
                InventoryData::assert((bool) $location->active, 'Ubicación inactiva.');
                if ($locationId === $to && in_array($location->type, ['warehouse', 'stand'], true)) {
                    app(InventoryLocationService::class)->assertStorage($location);
                }
                $balance = DB::table('inventory_balances')->where('product_id', $productId)->where('location_id', $locationId)->lockForUpdate()->first();
                $delta = $locationId === $from ? -$quantity : $quantity;
                $new = round((float) ($balance->quantity ?? 0) + $delta, 3);
                if ($new < 0) {
                    throw new InventoryException('Stock insuficiente.', 'INVENTORY_STOCK_INSUFFICIENT', ['product_id' => ['Disponible: '.($balance->quantity ?? 0).', solicitado: '.$quantity]]);
                }
                if ($balance) {
                    InventoryData::update('inventory_balances', $balance->id, ['quantity' => $new]);
                } else {
                    InventoryData::insert('inventory_balances', ['product_id' => $productId, 'location_id' => $locationId, 'quantity' => $new]);
                }
                if ($location->legacy_inventory_id) {
                    $inventory = Inventory::findOrFail($location->legacy_inventory_id);
                    $pivot = $inventory->products()->where('products.id', $productId)->first();
                    if ($pivot) {
                        $inventory->products()->updateExistingPivot($productId, ['quantity' => $new]);
                    } else {
                        $inventory->products()->attach($productId, ['quantity' => $new, 'price' => $product->purchase_cost ?? 0]);
                    }
                }
            }
            $id = InventoryData::insert('inventory_movements', [
                'product_id' => $productId, 'quantity' => $quantity, 'from_location_id' => $from, 'to_location_id' => $to,
                'movement_type' => $type, 'created_by' => $actor, 'notes' => $notes,
                'source_type' => $source, 'source_id' => $sourceId, 'reference' => $reference, 'occurred_at' => now(), 'recorded_at' => now(),
            ]);
            app(AuditService::class)->record($actor, $type, 'inventory_movement', $id, ['product_id' => $productId, 'quantity' => $quantity, 'from' => $from, 'to' => $to, 'notes' => $notes]);

            return $id;
        });
    }

    public function count(array $data, int $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $location = InventoryData::get('inventory_locations', $data['location_id']);
            InventoryData::assert((bool) $product->tracks_inventory, 'El artículo no controla inventario.');
            app(InventoryLocationService::class)->assertStorage($location);
            $balance = DB::table('inventory_balances')->where('product_id', $product->id)->where('location_id', $location->id)->lockForUpdate()->first();
            $before = (float) ($balance->quantity ?? 0);
            if ($before !== (float) $data['expected_quantity']) {
                throw new InventoryException('Las existencias cambiaron. Consulta el saldo y vuelve a capturar el conteo.', 'INVENTORY_COUNT_CONFLICT', [], 409);
            }
            if ($data['purpose'] === 'initial') {
                InventoryData::assert(! DB::table('inventory_movements')->where('product_id', $product->id)->where(fn ($q) => $q->where('from_location_id', $location->id)->orWhere('to_location_id', $location->id))->exists(), 'El artículo ya tiene movimientos en este almacén. Usa Corrección por conteo físico.');
            }
            $delta = round((float) $data['quantity'] - $before, 3);
            $movement = $delta == 0 ? null : $this->record($product->id, abs($delta), $delta < 0 ? $location->id : null, $delta > 0 ? $location->id : null, $delta < 0 ? 'adjustment_out' : 'adjustment_in', $actor, $data['notes'], 'inventory_count', null, $data['purpose'] === 'initial' ? 'Carga inicial' : 'Conteo físico');
            app(AuditService::class)->record($actor, 'inventory_count', 'product', $product->id, $data + ['previous_quantity' => $before, 'movement_id' => $movement]);

            return ['product_id' => $product->id, 'location_id' => $location->id, 'previous_quantity' => $before, 'quantity' => (float) $data['quantity'], 'difference' => $delta, 'movement_id' => $movement];
        });
    }

    public function adopt(int $locationId, int $actor): array
    {
        return DB::transaction(function () use ($locationId, $actor) {
            $location = InventoryData::get('inventory_locations', $locationId, true);
            InventoryData::assert($location->legacy_inventory_id && ! $location->adopted_at, 'Ubicación sin inventario anterior o ya importada.');
            InventoryData::assert(! DB::table('inventory_balances')->where('location_id', $locationId)->exists(), 'La ubicación ya tiene movimientos.');
            $inventory = Inventory::with('products')->findOrFail($location->legacy_inventory_id);
            foreach ($inventory->products->sortBy('id') as $product) {
                $quantity = (float) $product->pivot->quantity;
                InventoryData::assert($quantity >= 0, 'Saldo anterior negativo; conciliar antes de importar.');
                if ($quantity > 0) {
                    $this->record($product->id, $quantity, null, $locationId, 'adjustment_in', $actor, 'Saldo de apertura del inventario anterior', 'legacy_inventory', $inventory->id);
                }
            }
            InventoryData::update('inventory_locations', $locationId, ['adopted_at' => now()]);

            return ['location_id' => $locationId, 'imported' => true];
        });
    }

    public function reverse(int $id, int $actor, string $reason): int
    {
        return DB::transaction(function () use ($id, $actor, $reason) {
            $movement = InventoryData::get('inventory_movements', $id, true);
            InventoryData::assert(! DB::table('inventory_movements')->where('source_type', 'movement_correction')->where('source_id', $id)->exists(), 'Movimiento ya corregido.');

            return $this->record($movement->product_id, (float) $movement->quantity, $movement->to_location_id, $movement->from_location_id, 'correction', $actor, $reason, 'movement_correction', $id);
        });
    }
}
