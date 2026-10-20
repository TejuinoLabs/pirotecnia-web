<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    public function summary(): array
    {
        $demand = app(InventoryDemandService::class)->project();
        $catalog = Product::where('active', true)->get();
        app(ProductMatcherService::class)->loadAttributes($catalog);
        $stockContext = app(InventoryAvailabilityService::class)->context(null, null, null, $catalog->pluck('id'));
        $products = $catalog->map(fn ($p) => $p->attributesToArray() + app(InventoryAvailabilityService::class)->stock($p, null, null, null, $demand, null, $stockContext));
        $risks = [];
        $events = DB::table('events')->where('event_date', '>=', now())->whereNotIn('logistics_status', ['closed'])->orderBy('event_date')->limit(50)->get();
        $coverages = app(EventCoverageService::class)->coverageMany($events->pluck('id')->all());
        foreach ($events as $event) {
            $coverage = $coverages[$event->id];
            if ($coverage['status'] !== 'covered') {
                $risks[] = ['event_id' => $event->id, 'client_name' => $event->client_name, 'event_date' => $event->event_date, ...$coverage];
            }
        }
        $outside = DB::table('inventory_balances')->join('inventory_locations', 'inventory_locations.id', '=', 'location_id')->join('products', 'products.id', '=', 'product_id')->where('inventory_locations.type', '!=', 'warehouse')->where('products.is_returnable', true)->sum('quantity');
        $stands = DB::table('inventory_balances')->join('inventory_locations', 'inventory_locations.id', '=', 'location_id')->where('inventory_locations.type', 'stand')->sum('quantity');

        return ['inventory_value' => $products->sum(fn ($p) => $p['physical_stock'] * ($p['purchase_cost'] ?? 0)),
            'low_stock_products' => $products->filter(fn ($p) => $p['available_stock'] < $p['minimum_stock'])->count(),
            'upcoming_events' => $events->count(), 'deficit_events' => collect($risks)->where('status', 'critical')->count(),
            'pending_purchases' => DB::table('purchases')->whereIn('status', ['ordered', 'partial'])->count(),
            'equipment_outside' => (float) $outside, 'stand_stock' => (float) $stands,
            'protected_low_products' => $products->filter(fn ($p) => $p['protected_stock'] > 0 && $p['available_stock'] < $p['minimum_stock'])->count(),
            'event_risks' => $risks, 'critical_products' => $products->filter(fn ($p) => $p['available_stock'] <= $p['minimum_stock'] || $p['projected_stock'] < 0)->values()->all()];
    }
}
