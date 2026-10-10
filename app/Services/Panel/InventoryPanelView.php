<?php

namespace App\Services\Panel;

use App\Models\Product;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryPanelView
{
    public function render(Request $request, string $section, string $area)
    {
        $event = $request->route('event') === null ? null : (int) $request->route('event');
        $access = app(InventoryAccess::class);
        $required = match ($section) {
            'sales' => 'sales.view', 'preparation', 'events' => 'events.prepare', 'purchases' => 'purchases.view', 'audit' => 'audit.view', 'requirements' => 'events.view', default => 'inventory.view'
        };
        $access->authorize($request->user(), $required);
        $permissions = collect(['sales.view', 'sales.manage', 'inventory.view', 'inventory.adjust', 'inventory.dispatch', 'inventory.return', 'inventory.override_protected', 'purchases.view', 'purchases.create', 'purchases.receive', 'events.view', 'events.manage', 'events.prepare', 'packages.manage', 'rules.manage', 'audit.view'])->filter(fn ($p) => $access->allows($request->user(), $p))->values()->all();
        $catalogs = [];
        foreach (['categories', 'effects', 'colors', 'attribute_definitions', 'inventory_locations', 'product_profiles', 'product_roles', 'event_types'] as $table) {
            $catalogs[$table] = DB::table($table)->get(['id', 'name'])->toArray();
        }
        $catalogs['attribute_definitions'] = DB::table('attribute_definitions')->where('active', true)->get(['id', 'code', 'name', 'data_type'])->toArray();
        $catalogs['inventory_locations'] = DB::table('inventory_locations')->where('active', true)->get(['id', 'name', 'type', 'inventory_area'])->toArray();
        $catalogs['products'] = Product::orderBy('name')->get(['id', 'name', 'caliber', 'active', 'is_sellable', 'tracks_inventory', 'sale_price', 'purchase_cost'])->map(fn ($product) => ['id' => $product->id, 'name' => $product->display_name, 'active' => (bool) $product->active, 'is_sellable' => (bool) $product->is_sellable, 'tracks_inventory' => (bool) $product->tracks_inventory, 'sale_price' => $product->sale_price, 'purchase_cost' => $product->purchase_cost])->all();
        $catalogs['client_types'] = DB::table('client_types')->orderBy('name')->get(['id', 'name'])->toArray();
        $catalogs['inventories'] = DB::table('inventories')->get(['id', 'name'])->toArray();
        $catalogs['equipments'] = DB::table('equipments')->get(['id', 'name'])->toArray();
        $catalogs['packages'] = DB::table('packages')->get(['id', 'name'])->toArray();
        $catalogs['technicians'] = DB::table('users')->whereIn('id', DB::table('employees')->whereNotNull('user_id')->select('user_id'))->get(['id', 'name'])->toArray();

        $commerceStats = [];
        if (in_array($section, ['sales', 'purchases'], true)) {
            $table = $section;
            $commerceStats = [
                ['label' => 'Total de registros', 'value' => DB::table($table)->count(), 'icon' => $section === 'sales' ? 'point_of_sale' : 'shopping_cart', 'hint' => 'Registros del módulo'],
                ['label' => $section === 'sales' ? 'Ventas finalizadas' : 'Compras recibidas', 'value' => DB::table($table)->where('status', $section === 'sales' ? 'completed' : 'received')->count(), 'icon' => 'check_circle', 'hint' => 'Operaciones completadas', 'tone' => 'text-secondary'],
                ['label' => $section === 'sales' ? 'Borradores' : 'Pendientes de recibir', 'value' => DB::table($table)->whereIn('status', $section === 'sales' ? ['draft'] : ['draft', 'ordered', 'partial'])->count(), 'icon' => 'pending', 'hint' => 'Operaciones pendientes', 'tone' => 'text-warning'],
            ];
        }
        $view = in_array($section, ['sales', 'purchases'], true) ? 'panel.commerce.index' : ($area === 'settings' ? 'panel.settings.inventory-catalog' : 'panel.inventory.operations');
        $recordAction = match (true) {
            $request->routeIs('sales.create', 'purchases.create') => 'create',
            $request->routeIs('sales.edit', 'purchases.edit') => 'edit',
            $request->routeIs('sales.show', 'purchases.show') => 'show',
            default => '',
        };

        $summary = $section === 'inventory' ? app(InventoryDashboardService::class)->summary() : null;
        $criticalProducts = $summary ? \App\Support\PanelPagination::collection(collect($summary['critical_products'])->sortBy('id')->values(), 'critical_page') : null;

        return view($view, ['criticalProducts' => $criticalProducts, 'commerceStats' => $commerceStats, 'recordAction' => $recordAction, 'recordId' => $request->route('id'), 'presentation' => $area === 'settings' ? app(InventorySettingsPresentation::class)->forSection($section, $request->route('catalog')) : null] + ['area' => $area, 'selectedCatalog' => $request->route('catalog'), 'selectedPackage' => $request->route('package'), 'section' => $section, 'eventId' => $event, 'catalogs' => $catalogs, 'permissions' => $permissions, 'summary' => $summary]);
    }
}
