<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;

class Inventory extends Model
{
    const MAX_STOCK = 1000;

    const MIN_STOCK = 10;

    use HasFactory;

    public function products(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'productable')->withPivot(['quantity', 'price', 'check_employee', 'check_almacen'])->withTimestamps();
    }

    public static function updateQuantityProducts($event)
    {
        // Operational events can only leave stock through a confirmed dispatch.
        if (DB::table('event_requirements')->where('event_id', $event->id)->exists()
            || DB::table('inventory_locations')->where('legacy_inventory_id', 1)->whereNotNull('adopted_at')->exists()) {
            return;
        }
        $event->loadMissing(['products', 'equipments']);
        $inventory = Inventory::with('products')->find(1);

        if (! $inventory) {
            return;
        }

        foreach ($event->products as $product) {
            if ((bool) ($product->pivot->check_almacen ?? false)) {
                continue;
            }

            $inventoryProduct = $inventory->products->find($product->id);

            if (! $inventoryProduct) {
                continue;
            }

            $quantityInventory = (float) ($inventoryProduct->pivot->quantity ?? 0);
            $quantityEvent = (float) ($product->pivot->quantity ?? 0);

            $inventory->products()->updateExistingPivot($product->id, [
                'quantity' => max($quantityInventory - $quantityEvent, 0),
            ]);
        }

        $event->products->each(function ($product) use ($event) {
            $event->products()->updateExistingPivot($product->id, [
                'check_almacen' => true,
            ]);
        });

        $event->equipments->each(function ($equipment) use ($event) {
            $event->equipments()->updateExistingPivot($equipment->id, [
                'check_almacen' => true,
            ]);
        });
    }

    public static function reservedQuantityForProduct(int $productId): float
    {
        return (float) DB::table('productables')
            ->where('product_id', $productId)
            ->where('productable_type', Event::class)
            ->where('check_almacen', false)
            ->sum('quantity');
    }

    public static function availableQuantityForProduct(int $productId): float
    {
        $inventory = Inventory::with('products')->find(1);

        if (! $inventory) {
            return 0;
        }

        $inventoryProduct = $inventory->products->find($productId);
        $currentQuantity = (float) ($inventoryProduct?->pivot?->quantity ?? 0);

        return max($currentQuantity - self::reservedQuantityForProduct($productId), 0);
    }
}
