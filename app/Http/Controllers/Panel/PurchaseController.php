<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\InventoryPanelView;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryData;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request, InventoryPanelView $panel)
    {
        return $panel->render($request, 'purchases', 'purchases');
    }

    public function create(Request $request, InventoryPanelView $panel)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'purchases.create');

        return $this->index($request, $panel);
    }

    public function show(Request $request, InventoryPanelView $panel, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'purchases.view');
        InventoryData::get('purchases', $id);

        return $this->index($request, $panel);
    }

    public function edit(Request $request, InventoryPanelView $panel, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'purchases.create');
        InventoryData::get('purchases', $id);

        return $this->index($request, $panel);
    }

    public function store(Request $request)
    {
        app(\App\Services\Inventory\InventoryAccess::class)->authorize($request->user(), 'purchases.create');
        $result = app(\App\Http\Controllers\API\OperationalInventoryController::class)->createPurchase($request);

        return $request->expectsJson() ? $result : redirect()->route('purchases.index');
    }

    public function update(Request $request, int $id)
    {
        app(\App\Services\Inventory\InventoryAccess::class)->authorize($request->user(), 'purchases.create');
        $result = app(\App\Http\Controllers\API\OperationalInventoryController::class)->updatePurchase($request, $id);

        return $request->expectsJson() ? $result : redirect()->route('purchases.show', $id);
    }

    public function destroy(Request $request, int $id)
    {
        app(\App\Services\Inventory\InventoryAccess::class)->authorize($request->user(), 'purchases.create');
        $result = app(\App\Http\Controllers\API\OperationalInventoryController::class)->cancelPurchase($request, $id);

        return $request->expectsJson() ? $result : redirect()->route('purchases.index');
    }
}
