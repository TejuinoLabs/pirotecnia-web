<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleService
{
    public function validate(array $payload): array
    {
        return Validator::make($payload, [
            'date' => ['required', 'date'], 'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_type_id' => ['nullable', 'integer', 'exists:client_types,id'],
            'client_name' => ['required', 'string', 'max:255'], 'client_phone' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'], 'client_address' => ['nullable', 'string', 'max:255'],
            'warehouse_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'status' => ['required', 'in:draft,completed'], 'notes' => ['nullable', 'string', 'max:2000'],
            'discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'], 'tax' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1'], 'items.*' => ['array:product_id,quantity,unit_price'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ])->validate();
    }

    public function save(array $payload, int $actor, ?int $id = null): array
    {
        $data = $this->validate($payload);

        return DB::transaction(function () use ($data, $actor, $id) {
            $old = $id ? InventoryData::get('sales', $id, true) : null;
            if ($old) {
                InventoryData::assert($old->uses_operational_inventory && in_array($old->status, ['draft', 'completed'], true), 'Esta venta no admite edición.');
                InventoryData::assert($old->status !== 'completed' || $data['status'] === 'completed', 'Una venta finalizada se cancela; no vuelve a borrador.');
            }
            $location = InventoryData::get('inventory_locations', $data['warehouse_location_id']);
            app(InventoryLocationService::class)->assertStorage($location);
            $ids = collect($data['items'])->pluck('product_id');
            if ($id) {
                $ids = $ids->merge(DB::table('operational_sale_items')->where('sale_id', $id)->pluck('product_id'));
            }
            $products = Product::whereIn('id', $ids->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($old?->status === 'completed') {
                $this->restore($id, $actor, 'Corrección de venta #'.$id);
            }
            $subtotal = round(collect($data['items'])->sum(fn ($item) => round($item['quantity'] * $item['unit_price'], 2)), 2);
            $discount = (float) ($data['discount'] ?? 0);
            InventoryData::assert($discount <= $subtotal, 'El descuento no puede superar el subtotal.');
            $values = collect($data)->except('items')->all();
            $values['client_email'] = $values['client_email'] ?? '';
            $values['client_phone'] = $values['client_phone'] ?? '';
            $values['discount'] = $discount;
            $values['tax'] = $data['tax'] ?? 0;
            $values['subtotal'] = $subtotal;
            $values['total'] = round($subtotal - $discount + $values['tax'], 2);
            $values['uses_operational_inventory'] = true;
            if ($id) {
                InventoryData::update('sales', $id, $values);
                DB::table('operational_sale_items')->where('sale_id', $id)->delete();
            } else {
                $id = InventoryData::insert('sales', $values + ['created_by' => $actor]);
            }
            foreach (collect($data['items'])->sortBy('product_id') as $item) {
                $product = $products[$item['product_id']];
                InventoryData::assert((bool) $product->active && (bool) $product->is_sellable, 'El artículo '.$product->display_name.' no está disponible para venta.');
                InventoryData::insert('operational_sale_items', ['sale_id' => $id, 'product_name' => $product->display_name, 'unit' => $product->unit, 'subtotal' => round($item['quantity'] * $item['unit_price'], 2), ...$item]);
                if ($data['status'] === 'completed' && $product->tracks_inventory) {
                    $stock = app(InventoryAvailabilityService::class)->stock($product, $location->id);
                    InventoryData::assert($item['quantity'] <= $stock['available_stock'], 'Existencias disponibles insuficientes para '.$product->display_name.'.', 'INVENTORY_STOCK_INSUFFICIENT');
                    app(InventoryMovementService::class)->record($product->id, (float) $item['quantity'], $location->id, null, 'sale', $actor, 'Venta #'.$id, 'sale', $id, 'Venta #'.$id);
                }
            }
            app(AuditService::class)->record($actor, $old ? 'sale_updated' : 'sale_created', 'sale', $id, $values, $old ? (array) $old : []);

            return $this->show($id);
        });
    }

    private function restore(int $id, int $actor, string $reason): void
    {
        $movements = DB::table('inventory_movements')->where('source_type', 'sale')->where('source_id', $id)->where('movement_type', 'sale')->orderBy('product_id')->get();
        foreach ($movements as $movement) {
            if (! DB::table('inventory_movements')->where('source_type', 'movement_correction')->where('source_id', $movement->id)->exists()) {
                app(InventoryMovementService::class)->reverse($movement->id, $actor, $reason);
            }
        }
    }

    public function cancel(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $sale = InventoryData::get('sales', $id, true);
            InventoryData::assert((bool) $sale->uses_operational_inventory, 'La venta anterior requiere conciliación manual.');
            if ($sale->status === 'cancelled') {
                return $this->show($id);
            }
            InventoryData::assert(in_array($sale->status, ['draft', 'completed'], true), 'La venta no admite cancelación.');
            DB::table('products')->whereIn('id', DB::table('operational_sale_items')->where('sale_id', $id)->select('product_id'))->orderBy('id')->lockForUpdate()->get();
            if ($sale->status === 'completed') {
                $this->restore($id, $actor, 'Cancelación de venta #'.$id);
            }
            InventoryData::update('sales', $id, ['status' => 'cancelled']);
            app(AuditService::class)->record($actor, 'sale_cancelled', 'sale', $id, [], (array) $sale);

            return $this->show($id);
        });
    }

    public function show(int $id): array
    {
        return ['sale' => (array) InventoryData::get('sales', $id), 'items' => DB::table('operational_sale_items')->where('sale_id', $id)->orderBy('id')->get()->toArray()];
    }
}
