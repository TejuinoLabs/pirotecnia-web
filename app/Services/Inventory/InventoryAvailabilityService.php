<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryAvailabilityService
{
    public function stock(Product $product, ?int $locationId = null, ?object $event = null, ?int $ignoreAllocation = null, ?array $demandProjection = null, ?string $inventoryArea = null): array
    {
        $balances = DB::table('inventory_balances')->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_balances.location_id')->where('product_id', $product->id);
        if ($inventoryArea) {
            $balances->where('inventory_locations.inventory_area', $inventoryArea)->whereIn('inventory_locations.type', ['warehouse', 'stand'])->where('inventory_locations.active', true);
        }
        if ($locationId) {
            $balances->where('location_id', $locationId);
        } elseif (! $inventoryArea) {
            $balances->where('inventory_locations.type', 'warehouse')->where('inventory_locations.active', true);
        }
        $physical = (float) $balances->sum('quantity');
        $reserved = DB::table('requirement_allocations')->where('product_id', $product->id)->whereIn('status', ['reserved', 'picked']);
        if ($locationId) {
            $reserved->where('warehouse_location_id', $locationId);
        }
        if ($inventoryArea) {
            $reserved->whereIn('warehouse_location_id', DB::table('inventory_locations')->where('inventory_area', $inventoryArea)->select('id'));
        }
        if ($ignoreAllocation) {
            $reserved->where('id', '!=', $ignoreAllocation);
        }
        $reservedStock = (float) $reserved->sum('quantity');
        $protection = app(InventoryProtectionService::class)->evaluate($product, $physical, $event);
        $incoming = DB::table('operational_purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_id')->where('product_id', $product->id)->whereIn('purchases.status', ['ordered', 'partial']);
        if ($locationId) {
            $incoming->where('purchases.destination_location_id', $locationId);
        }
        if ($inventoryArea) {
            $incoming->whereIn('purchases.destination_location_id', DB::table('inventory_locations')->where('inventory_area', $inventoryArea)->select('id'));
        }
        $incomingStock = (float) $incoming->selectRaw('COALESCE(SUM(quantity - received_quantity), 0) as incoming')->value('incoming');
        $demandProjection ??= app(InventoryDemandService::class)->project($locationId);
        $demand = (float) ($demandProjection[$product->id] ?? 0);
        $available = max(0, $physical - $reservedStock - $protection['protected_stock']);

        return ['product_id' => $product->id, 'physical_stock' => $physical, 'reserved_stock' => $reservedStock,
            'protected_stock' => $protection['protected_stock'], 'available_stock' => $available,
            'future_demand' => $demand, 'incoming_stock' => $incomingStock,
            'projected_stock' => $physical - $protection['protected_stock'] - $demand + $incomingStock,
            'protection' => $protection];
    }
}
