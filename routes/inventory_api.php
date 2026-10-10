<?php

use App\Http\Controllers\API\InventoryCatalogController as Catalog;
use App\Http\Controllers\API\OperationalInventoryController as Inventory;
use App\Http\Middleware\InventoryPermission;
use Illuminate\Support\Facades\Route;

return function (string $auth, string $prefix = '') {
    Route::middleware($auth)->prefix($prefix)->group(function () {
        foreach ([['get', 'sales', 'index'], ['post', 'sales', 'store'], ['get', 'sales/{id}', 'show'], ['put', 'sales/{id}', 'update'], ['delete', 'sales/{id}', 'destroy']] as [$method, $uri, $action]) {
            Route::$method($uri, [\App\Http\Controllers\API\SaleController::class, $action]);
        }
        $routes = [
            ['get', 'inventory/dashboard', 'dashboard', 'inventory.view'], ['get', 'inventory/audit', 'audits', 'audit.view'],
            ['get', 'products', 'products', 'inventory.view'], ['post', 'products', 'saveProduct', 'packages.manage'],
            ['get', 'products/{product}', 'product', 'inventory.view'], ['put', 'products/{product}', 'saveProduct', 'packages.manage'],
            ['get', 'inventory', 'inventory', 'inventory.view'], ['get', 'inventory/movements', 'movements', 'inventory.view'],
            ['get', 'inventory/balances/{product}', 'balance', 'inventory.view'], ['post', 'inventory/counts', 'count', 'inventory.adjust'],
            ['post', 'inventory/adjustments', 'adjustment', 'inventory.adjust'], ['post', 'inventory/movements/{movement}/reverse', 'correction', 'inventory.adjust'],
            ['post', 'inventory/locations/{location}/adopt', 'adopt', 'inventory.adjust'],
            ['get', 'purchases', 'purchases', 'purchases.view'], ['post', 'purchases', 'createPurchase', 'purchases.create'],
            ['get', 'purchases/{purchase}', 'purchase', 'purchases.view'], ['put', 'purchases/{purchase}', 'updatePurchase', 'purchases.create'],
            ['delete', 'purchases/{purchase}', 'cancelPurchase', 'purchases.create'],
            ['post', 'purchases/{purchase}/receive', 'receivePurchase', 'purchases.receive'], ['post', 'purchases/{purchase}/cancel', 'cancelPurchase', 'purchases.create'],
            ['get', 'packages', 'packages', 'events.view'], ['post', 'packages', 'savePackage', 'packages.manage'],
            ['get', 'packages/{package}', 'package', 'events.view'], ['put', 'packages/{package}', 'savePackage', 'packages.manage'],
            ['delete', 'packages/{package}', 'deletePackage', 'packages.manage'], ['post', 'packages/{package}/requirements', 'requirement', 'packages.manage'],
            ['post', 'events', 'createEvent', 'events.manage'], ['post', 'events/{event}/requirements/snapshot', 'snapshot', 'events.prepare'],
            ['get', 'events/{event}/inventory-coverage', 'coverage', 'inventory.view'],
            ['get', 'events/{event}/requirements/{requirement}/candidates', 'candidates', 'events.prepare'],
            ['post', 'events/{event}/requirements/{requirement}/allocations', 'allocate', 'events.prepare'],
            ['post', 'requirement-allocations/{allocation}/replace', 'replace', 'events.prepare'],
            ['get', 'event-preparations', 'preparations', 'events.prepare'], ['get', 'events/{event}/preparation', 'preparation', 'events.prepare'],
            ['post', 'events/{event}/preparation/start', 'startPreparation', 'events.prepare'], ['post', 'events/{event}/preparation/complete', 'completePreparation', 'events.prepare'],
            ['post', 'events/{event}/pickings', 'createPicking', 'events.prepare'], ['get', 'events/{event}/pickings/{picking}', 'picking', 'events.prepare'],
            ['post', 'events/{event}/pickings/{picking}/items/{item}/confirm', 'confirmPickingItem', 'events.prepare'],
            ['post', 'events/{event}/pickings/{picking}/complete', 'completePicking', 'events.prepare'],
            ['get', 'events/{event}/dispatches', 'dispatches', 'inventory.view'], ['post', 'events/{event}/dispatches', 'createDispatch', 'inventory.dispatch'],
            ['get', 'event-dispatches/{dispatch}', 'dispatch', 'inventory.view'], ['post', 'event-dispatches/{dispatch}/confirm', 'confirmDispatch', 'inventory.dispatch'],
            ['post', 'events/{event}/returns', 'createReturn', 'inventory.return'], ['post', 'event-returns/{return}/confirm', 'confirmReturn', 'inventory.return'],
        ];
        foreach ($routes as [$verb, $uri, $method, $permission]) {
            Route::$verb($uri, [Inventory::class, $method])->middleware(InventoryPermission::class.':'.$permission);
        }
        // Ownership checks are performed by the technician service; no warehouse access is needed.
        Route::get('technician/events/{event}/dispatch', [Inventory::class, 'technicianDispatch'])->middleware('throttle:120,1');
        Route::post('technician/dispatches/{dispatch}/receive', [Inventory::class, 'receiveTechnician'])->middleware('throttle:120,1');
        foreach (['categories', 'attributes', 'effects', 'colors', 'locations', 'profiles', 'inventory-protection-rules', 'allocation-rules'] as $catalog) {
            $uri = in_array($catalog, ['inventory-protection-rules', 'allocation-rules'], true) ? $catalog : 'inventory/catalogs/'.$catalog;
            foreach (['get' => 'index', 'post' => 'save'] as $verb => $method) {
                Route::$verb($uri, [Catalog::class, $method])->defaults('catalog', $catalog)->middleware(InventoryPermission::class.':'.($verb === 'get' ? 'inventory.view' : 'rules.manage'));
            }
            foreach (['get' => 'show', 'put' => 'save', 'delete' => 'destroy'] as $verb => $method) {
                Route::$verb($uri.'/{id}', [Catalog::class, $method])->defaults('catalog', $catalog)->middleware(InventoryPermission::class.':'.($verb === 'get' ? 'inventory.view' : 'rules.manage'));
            }
        }
    });
};
