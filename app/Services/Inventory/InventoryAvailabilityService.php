<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryAvailabilityService
{
    /** A calculation-local snapshot; callers must rebuild it after inventory writes. */
    public function context(?int $locationId = null, ?int $ignoreAllocation = null, ?string $inventoryArea = null, ?iterable $productIds = null): array
    {
        $balances = DB::table('inventory_balances')->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_balances.location_id');
        if ($inventoryArea) {
            $balances->where('inventory_locations.inventory_area', $inventoryArea)->whereIn('inventory_locations.type', ['warehouse', 'stand'])->where('inventory_locations.active', true);
        }
        if ($locationId) {
            $balances->where('location_id', $locationId);
        } elseif (! $inventoryArea) {
            $balances->where('inventory_locations.type', 'warehouse')->where('inventory_locations.active', true);
        }
        $reserved = DB::table('requirement_allocations')->whereIn('status', ['reserved', 'picked']);
        if ($locationId) {
            $reserved->where('warehouse_location_id', $locationId);
        }
        if ($inventoryArea) {
            $reserved->whereIn('warehouse_location_id', DB::table('inventory_locations')->where('inventory_area', $inventoryArea)->select('id'));
        }
        if ($ignoreAllocation) {
            $reserved->where('id', '!=', $ignoreAllocation);
        }
        $incoming = DB::table('operational_purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_id')->whereIn('purchases.status', ['ordered', 'partial']);
        if ($locationId) {
            $incoming->where('purchases.destination_location_id', $locationId);
        }
        if ($inventoryArea) {
            $incoming->whereIn('purchases.destination_location_id', DB::table('inventory_locations')->where('inventory_area', $inventoryArea)->select('id'));
        }
        if ($productIds !== null) {
            $balances->whereIn('product_id', $productIds);
            $reserved->whereIn('product_id', $productIds);
            $incoming->whereIn('product_id', $productIds);
        }

        return [
            'physical' => $balances->selectRaw('product_id, SUM(quantity) as total')->groupBy('product_id')->pluck('total', 'product_id'),
            'reserved' => $reserved->selectRaw('product_id, SUM(quantity) as total')->groupBy('product_id')->pluck('total', 'product_id'),
            'incoming' => $incoming->selectRaw('product_id, SUM(quantity - received_quantity) as total')->groupBy('product_id')->pluck('total', 'product_id'),
            'protection_rules' => DB::table('inventory_protection_rules')->where('active', true)->orderByDesc('priority')->get(),
        ];
    }

    public function stock(Product $product, ?int $locationId = null, ?object $event = null, ?int $ignoreAllocation = null, ?array $demandProjection = null, ?string $inventoryArea = null, ?array $context = null): array
    {
        $context ??= $this->context($locationId, $ignoreAllocation, $inventoryArea, [$product->id]);
        $physical = (float) ($context['physical'][$product->id] ?? 0);
        $reservedStock = (float) ($context['reserved'][$product->id] ?? 0);
        $incomingStock = (float) ($context['incoming'][$product->id] ?? 0);
        $protection = app(InventoryProtectionService::class)->evaluate($product, $physical, $event, $context['protection_rules']);
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
