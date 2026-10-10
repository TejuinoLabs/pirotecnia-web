<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryAccess;
use Illuminate\Http\Request;

/** Legacy URL compatibility; all purchase operations share the inventory service. */
class PurchaseController extends Controller
{
    private function authorize(Request $request, string $permission): OperationalInventoryController
    {
        app(InventoryAccess::class)->authorize($request->user(), $permission);

        return app(OperationalInventoryController::class);
    }

    public function index(Request $request)
    {
        return $this->authorize($request, 'purchases.view')->purchases($request);
    }

    public function show(Request $request, int $id)
    {
        return $this->authorize($request, 'purchases.view')->purchase($id);
    }

    public function store(Request $request)
    {
        return $this->authorize($request, 'purchases.create')->createPurchase($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->authorize($request, 'purchases.create')->updatePurchase($request, $id);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->authorize($request, 'purchases.create')->cancelPurchase($request, $id);
    }
}
