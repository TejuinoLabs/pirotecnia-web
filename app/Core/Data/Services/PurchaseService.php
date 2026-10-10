<?php

namespace App\Core\Data\Services;

use App\Core\Data\Entities\Purchase;
use App\Models\Inventory as ModelsInventory;
use App\Models\Purchase as ModelsPurchase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function all(): Collection
    {
        try {
            $eloquentPurchases = ModelsPurchase::with(['products', 'equipments'])
                ->latest('date')
                ->latest('id')
                ->get();
            $purchases = $eloquentPurchases->map(function ($eloquentPurchase) {
                return Purchase::fromPurchase($eloquentPurchase);
            });

            return $purchases;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function find($purchaseId): ?Purchase
    {
        try {
            $eloquentPurchase = ModelsPurchase::with(['products', 'equipments'])->findOrFail($purchaseId);
            $purchase = Purchase::fromPurchase($eloquentPurchase);

            return $purchase;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function create(Purchase $purchase): ?Purchase
    {
        try {
            return DB::transaction(function () use ($purchase) {
                $eloquentPurchase = new ModelsPurchase;
                $eloquentPurchase->fill([
                    'user_id' => $purchase->user_id,
                    'date' => $purchase->date,
                    'supplier_name' => $purchase->supplier_name,
                    'payment_method' => $purchase->payment_method,
                    'reference' => $purchase->reference,
                    'observations' => $purchase->observations,
                    'status' => $purchase->status ?? 'confirmed',
                    'discount_enabled' => (bool) $purchase->discount_enabled,
                    'discount_rate' => $purchase->discount_enabled ? (float) $purchase->discount_rate : 0,
                    'iva_enabled' => (bool) $purchase->iva_enabled,
                    'iva_rate' => $purchase->iva_enabled ? (float) $purchase->iva_rate : 0,
                    'credit_limit_enabled' => (bool) $purchase->credit_limit_enabled,
                    'credit_limit_amount' => $purchase->credit_limit_enabled
                        ? $purchase->credit_limit_amount
                        : null,
                ]);
                $eloquentPurchase->save();

                $products = $this->normalizeProducts($purchase->products);
                $equipments = $this->normalizeEquipments($purchase->equipments);
                $this->syncPurchaseProducts($eloquentPurchase, $products);
                $this->syncPurchaseEquipments($eloquentPurchase, $equipments);

                if (($purchase->status ?? 'confirmed') === 'confirmed') {
                    $location = DB::table('inventory_locations')->where('legacy_inventory_id', 1)->whereNotNull('adopted_at')->first();
                    $operationalProducts = $products;
                    if ($location) {
                        foreach ($equipments as $equipment) {
                            $mapped = \App\Models\Product::where('legacy_equipment_id', $equipment['equipment_id'])->first();
                            \App\Services\Inventory\InventoryData::assert($mapped !== null, 'Vincula el equipo a un artículo inventariable antes de recibirlo.');
                            $operationalProducts = $operationalProducts->push(['product_id' => $mapped->id, 'quantity' => $equipment['quantity'], 'price' => $equipment['price']]);
                        }
                    }
                    if ($location && $operationalProducts->isNotEmpty()) {
                        $eloquentPurchase->forceFill(['uses_operational_inventory' => true, 'status' => 'ordered'])->save();
                        $items = [];
                        foreach ($operationalProducts as $product) {
                            $id = \App\Services\Inventory\InventoryData::insert('operational_purchase_items', ['purchase_id' => $eloquentPurchase->id, 'product_id' => $product['product_id'], 'quantity' => $product['quantity'], 'unit_cost' => $product['price']]);
                            $items[] = ['purchase_item_id' => $id, 'quantity' => $product['quantity']];
                        }
                        app(\App\Services\Inventory\PurchaseReceiptService::class)->receive($eloquentPurchase->id, ['warehouse_location_id' => $location->id, 'items' => $items, 'notes' => 'Recepción inmediata de compra capturada en panel anterior'], $purchase->user_id);
                    } else {
                        $this->increaseMainInventory($products);
                    }
                }

                $eloquentPurchase->load(['products', 'equipments']);

                return Purchase::fromPurchase($eloquentPurchase);
            });
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function update($purchase): ?Purchase
    {
        try {
            $eloquentPurchase = ModelsPurchase::findOrFail($purchase->id);
            $eloquentPurchase->fill([
                'user_id' => $purchase->user_id,
                'date' => $purchase->date,
                'supplier_name' => $purchase->supplier_name,
                'payment_method' => $purchase->payment_method,
                'reference' => $purchase->reference,
                'observations' => $purchase->observations,
                'status' => $purchase->status ?? 'confirmed',
                'discount_enabled' => (bool) $purchase->discount_enabled,
                'discount_rate' => $purchase->discount_enabled ? (float) $purchase->discount_rate : 0,
                'iva_enabled' => (bool) $purchase->iva_enabled,
                'iva_rate' => $purchase->iva_enabled ? (float) $purchase->iva_rate : 0,
                'credit_limit_enabled' => (bool) $purchase->credit_limit_enabled,
                'credit_limit_amount' => $purchase->credit_limit_enabled
                    ? $purchase->credit_limit_amount
                    : null,
            ]);
            $eloquentPurchase->save();

            return $purchase;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete($purchaseId): bool
    {
        try {
            ModelsPurchase::destroy($purchaseId);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPurchaseByProductId($productId): Collection
    {
        try {
            $eloquentPurchases = ModelsPurchase::whereHas('products', function ($query) use ($productId) {
                $query->where('products.id', $productId);
            })->with(['products', 'equipments'])->get();
            $purchases = $eloquentPurchases->map(function ($eloquentPurchase) {
                return Purchase::fromPurchase($eloquentPurchase);
            });

            return $purchases;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function searchPurchases(string $searchTerm): Collection
    {
        try {
            $eloquentPurchases = ModelsPurchase::with(['products', 'equipments'])
                ->where('supplier_name', 'like', '%'.$searchTerm.'%')
                ->orWhere('reference', 'like', '%'.$searchTerm.'%')
                ->get();

            return $eloquentPurchases->map(fn ($eloquentPurchase) => Purchase::fromPurchase($eloquentPurchase));
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    private function normalizeProducts(Collection $products): Collection
    {
        return $products
            ->filter(fn ($product) => (int) ($product['product_id'] ?? 0) > 0 && (float) ($product['quantity'] ?? 0) > 0)
            ->groupBy(fn ($product) => (int) $product['product_id'])
            ->map(function (Collection $items, int $productId) {
                $lastItem = $items->last();

                return [
                    'product_id' => $productId,
                    'quantity' => (float) $items->sum(fn ($item) => (float) ($item['quantity'] ?? 0)),
                    'price' => (float) ($lastItem['price'] ?? 0),
                ];
            })
            ->values();
    }

    private function normalizeEquipments(Collection $equipments): Collection
    {
        return $equipments
            ->filter(fn ($equipment) => (int) ($equipment['equipment_id'] ?? 0) > 0 && (float) ($equipment['quantity'] ?? 0) > 0)
            ->groupBy(fn ($equipment) => (int) $equipment['equipment_id'])
            ->map(function (Collection $items, int $equipmentId) {
                $lastItem = $items->last();

                return [
                    'equipment_id' => $equipmentId,
                    'quantity' => (float) $items->sum(fn ($item) => (float) ($item['quantity'] ?? 0)),
                    'price' => (float) ($lastItem['price'] ?? 0),
                ];
            })
            ->values();
    }

    private function syncPurchaseProducts(ModelsPurchase $purchase, Collection $products): void
    {
        $purchase->products()->sync(
            $products
                ->mapWithKeys(fn ($product) => [
                    $product['product_id'] => [
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                    ],
                ])
                ->all()
        );
    }

    private function syncPurchaseEquipments(ModelsPurchase $purchase, Collection $equipments): void
    {
        $purchase->equipments()->sync(
            $equipments
                ->mapWithKeys(fn ($equipment) => [
                    $equipment['equipment_id'] => [
                        'quantity' => $equipment['quantity'],
                        'price' => $equipment['price'],
                    ],
                ])
                ->all()
        );
    }

    private function increaseMainInventory(Collection $products): void
    {
        $inventory = ModelsInventory::with('products')->find(1);

        if (! $inventory) {
            $inventory = new ModelsInventory;
            $inventory->id = 1;
            $inventory->name = 'Polvorin 1';
            $inventory->location = 'Rancho el Tequeque';
            $inventory->save();
            $inventory->load('products');
        }

        foreach ($products as $product) {
            $inventoryProduct = $inventory->products->firstWhere('id', $product['product_id']);

            if ($inventoryProduct) {
                $inventory->products()->updateExistingPivot($product['product_id'], [
                    'quantity' => (float) ($inventoryProduct->pivot->quantity ?? 0) + $product['quantity'],
                    'price' => $product['price'],
                ]);

                continue;
            }

            $inventory->products()->attach($product['product_id'], [
                'quantity' => $product['quantity'],
                'price' => $product['price'],
            ]);
        }
    }
}
