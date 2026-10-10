<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseReceiptService
{
    public function validate(array $payload, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return Validator::make($payload, [
            'purchase_date' => [$required, 'date'], 'supplier_name' => ['nullable', 'string', 'max:255'],
            'expected_date' => ['nullable', 'date'], 'status' => ['sometimes', $update ? 'in:draft,ordered' : 'in:draft,ordered,received'],
            'warehouse_location_id' => ['required_if:status,received', 'nullable', 'integer', 'exists:inventory_locations,id'],
            'tax' => ['numeric', 'min:0', 'decimal:0,2'], 'notes' => ['nullable', 'string', 'max:2000'], 'observations' => ['nullable', 'string', 'max:2000'],
            'items' => [$required, 'array', 'min:1'], 'items.*' => ['array:product_id,quantity,unit_cost'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'], 'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ])->validate();
    }

    public function create(array $data, int $actor): array
    {
        $data = $this->validate($data);

        return DB::transaction(function () use ($data, $actor) {
            if (! empty($data['warehouse_location_id'])) {
                app(InventoryLocationService::class)->assertStorage(InventoryData::get('inventory_locations', $data['warehouse_location_id']));
            }
            $subtotal = round(collect($data['items'])->sum(fn ($i) => round($i['quantity'] * $i['unit_cost'], 2)), 2);
            $purchase = new Purchase;
            $purchase->forceFill([
                'user_id' => $actor, 'date' => $data['purchase_date'], 'supplier_name' => $data['supplier_name'] ?? null,
                'expected_date' => $data['expected_date'] ?? null, 'status' => ($data['status'] ?? 'ordered') === 'received' ? 'ordered' : ($data['status'] ?? 'ordered'),
                'observations' => $data['notes'] ?? null, 'destination_location_id' => $data['warehouse_location_id'] ?? null, 'subtotal' => $subtotal, 'tax' => $data['tax'] ?? 0,
                'total' => $subtotal + ($data['tax'] ?? 0), 'uses_operational_inventory' => true,
            ])->save();
            foreach ($data['items'] as $item) {
                InventoryData::insert('operational_purchase_items', ['purchase_id' => $purchase->id, ...$item]);
            }
            app(AuditService::class)->record($actor, 'purchase_created', 'purchase', $purchase->id, $data);

            if (($data['status'] ?? null) === 'received') {
                $items = DB::table('operational_purchase_items')->where('purchase_id', $purchase->id)->get()->map(fn ($item) => ['purchase_item_id' => $item->id, 'quantity' => (float) $item->quantity])->all();

                return $this->receive($purchase->id, ['warehouse_location_id' => $data['warehouse_location_id'], 'items' => $items, 'notes' => $data['notes'] ?? 'Recepción al registrar la compra'], $actor);
            }

            return $this->show($purchase->id);
        });
    }

    public function show(int $id): array
    {
        $purchase = InventoryData::get('purchases', $id);

        return ['purchase' => (array) $purchase,
            'items' => DB::table('operational_purchase_items')->join('products', 'products.id', '=', 'product_id')->where('purchase_id', $id)->select('operational_purchase_items.*', 'products.name', 'products.caliber', 'products.unit')->orderBy('operational_purchase_items.id')->get()->map(function ($item) {
                $item->product_name = (new Product(['name' => $item->name, 'caliber' => $item->caliber]))->display_name;

                return $item;
            })->all(),
            'receipts' => DB::table('purchase_receipts')->where('purchase_id', $id)->get()->toArray()];
    }

    public function receive(int $id, array $data, int $actor): array
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $purchase = InventoryData::get('purchases', $id, true);
            InventoryData::assert($purchase->uses_operational_inventory && in_array($purchase->status, ['ordered', 'partial'], true), 'La compra no admite recepción.');
            $location = InventoryData::get('inventory_locations', $data['warehouse_location_id']);
            app(InventoryLocationService::class)->assertStorage($location);
            $receipt = InventoryData::insert('purchase_receipts', ['purchase_id' => $id, 'warehouse_location_id' => $location->id, 'received_at' => now(), 'received_by' => $actor, 'notes' => $data['notes'] ?? null]);
            $ids = collect($data['items'])->pluck('purchase_item_id');
            InventoryData::assert($ids->unique()->count() === $ids->count(), 'Líneas duplicadas.');
            DB::table('products')->whereIn('id', DB::table('operational_purchase_items')->whereIn('id', $ids)->select('product_id'))->orderBy('id')->lockForUpdate()->get();
            foreach (collect($data['items'])->sortBy('purchase_item_id') as $entry) {
                $item = InventoryData::get('operational_purchase_items', $entry['purchase_item_id'], true);
                InventoryData::assert($item->purchase_id === $id && $entry['quantity'] > 0 && $entry['quantity'] <= (float) $item->quantity - (float) $item->received_quantity, 'Cantidad recibida excede la compra.');
                InventoryData::insert('purchase_receipt_items', ['purchase_receipt_id' => $receipt, 'purchase_item_id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $entry['quantity'], 'unit_cost' => $item->unit_cost]);
                if (Product::findOrFail($item->product_id)->tracks_inventory) {
                    app(InventoryMovementService::class)->record($item->product_id, (float) $entry['quantity'], null, $location->id, 'purchase_receipt', $actor, $data['notes'] ?? 'Recepción de compra', 'purchase_receipt', $receipt, 'Compra #'.$id);
                }
                InventoryData::update('operational_purchase_items', $item->id, ['received_quantity' => (float) $item->received_quantity + $entry['quantity']]);
                DB::table('products')->where('id', $item->product_id)->update(['purchase_cost' => $item->unit_cost]);
            }
            $pending = DB::table('operational_purchase_items')->where('purchase_id', $id)->whereColumn('received_quantity', '<', 'quantity')->exists();
            InventoryData::update('purchases', $id, ['status' => $pending ? 'partial' : 'received', 'destination_location_id' => $location->id]);
            app(AuditService::class)->record($actor, 'purchase_received', 'purchase', $id, $data);

            return ['receipt_id' => $receipt, ...$this->show($id)];
        });
    }

    public function cancel(int $id, int $actor): array
    {
        return DB::transaction(function () use ($id, $actor) {
            $purchase = InventoryData::get('purchases', $id, true);
            if ($purchase->uses_operational_inventory && $purchase->status === 'cancelled') {
                return $this->show($id);
            }
            InventoryData::assert($purchase->uses_operational_inventory && in_array($purchase->status, ['draft', 'ordered', 'partial', 'received'], true), 'La compra no admite cancelación.');
            // Received quantities remain in Kardex; only the outstanding order is cancelled.
            InventoryData::update('purchases', $id, ['status' => 'cancelled']);
            app(AuditService::class)->record($actor, 'purchase_cancelled', 'purchase', $id);

            return $this->show($id);
        });
    }

    public function update(int $id, array $data, int $actor): array
    {
        $data = $this->validate($data, true);

        return DB::transaction(function () use ($id, $data, $actor) {
            $purchase = InventoryData::get('purchases', $id, true);
            InventoryData::assert($purchase->uses_operational_inventory && in_array($purchase->status, ['draft', 'ordered', 'partial', 'received'], true), 'Esta compra no admite edición.');
            $hasReceipts = DB::table('purchase_receipts')->where('purchase_id', $id)->exists();
            if (array_key_exists('warehouse_location_id', $data)) {
                InventoryData::assert(! $hasReceipts, 'No cambies el destino de una compra con recepciones.');
                if ($data['warehouse_location_id']) {
                    app(InventoryLocationService::class)->assertStorage(InventoryData::get('inventory_locations', $data['warehouse_location_id']));
                }
            }
            InventoryData::assert(! $hasReceipts || ! isset($data['items']), 'Los conceptos recibidos no se editan. Puedes actualizar los datos de la compra.');
            InventoryData::assert(! $hasReceipts || ! isset($data['status']) || $data['status'] === $purchase->status, 'No se puede cambiar el estado de una compra recibida.');
            if (isset($data['items'])) {
                DB::table('operational_purchase_items')->where('purchase_id', $id)->delete();
                foreach ($data['items'] as $item) {
                    InventoryData::insert('operational_purchase_items', ['purchase_id' => $id, ...$item]);
                }
            }
            $values = collect($data)->except(['items', 'purchase_date', 'notes', 'warehouse_location_id'])->all();
            if (array_key_exists('warehouse_location_id', $data)) {
                $values['destination_location_id'] = $data['warehouse_location_id'];
            }
            if (isset($data['purchase_date'])) {
                $values['date'] = $data['purchase_date'];
            }
            if (array_key_exists('notes', $data)) {
                $values['observations'] = $data['notes'];
            }
            $subtotal = isset($data['items']) ? round(collect($data['items'])->sum(fn ($item) => round($item['quantity'] * $item['unit_cost'], 2)), 2) : (float) $purchase->subtotal;
            $values['subtotal'] = $subtotal;
            $values['total'] = round($subtotal + (float) ($data['tax'] ?? $purchase->tax), 2);
            InventoryData::update('purchases', $id, $values);
            app(AuditService::class)->record($actor, 'purchase_updated', 'purchase', $id, $values, (array) $purchase);

            return $this->show($id);
        });
    }
}
