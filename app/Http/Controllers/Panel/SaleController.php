<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\InventoryPanelView;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryData;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request, InventoryPanelView $panel)
    {
        return $panel->render($request, 'sales', 'sales');
    }

    public function create(Request $request, InventoryPanelView $panel)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.manage');

        return $this->index($request, $panel);
    }

    public function show(Request $request, InventoryPanelView $panel, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.view');
        InventoryData::get('sales', $id);

        return $this->index($request, $panel);
    }

    public function edit(Request $request, InventoryPanelView $panel, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.manage');
        InventoryData::get('sales', $id);

        return $this->index($request, $panel);
    }

    public function store(Request $request)
    {
        $result = app(\App\Http\Controllers\API\SaleController::class)->store($request);

        return $request->expectsJson() ? $result : redirect()->route('sales.index');
    }

    public function update(Request $request, int $id)
    {
        $result = app(\App\Http\Controllers\API\SaleController::class)->update($request, $id);

        return $request->expectsJson() ? $result : redirect()->route('sales.show', $id);
    }

    public function destroy(Request $request, int $id)
    {
        $result = app(\App\Http\Controllers\API\SaleController::class)->destroy($request, $id);

        return $request->expectsJson() ? $result : redirect()->route('sales.index');
    }
}
