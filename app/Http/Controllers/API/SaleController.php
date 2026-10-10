<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Inventory\IdempotencyService;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.view');
        $data = $request->validate(['status' => ['nullable', 'in:draft,completed,cancelled,legacy'], 'q' => ['nullable', 'string', 'max:255']]);

        return DB::table('sales')->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? null, fn ($query, $search) => $query->where('client_name', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate(15)->withQueryString();
    }

    public function show(Request $request, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.view');

        return app(SaleService::class)->show($id);
    }

    public function store(Request $request)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.manage');
        $data = app(SaleService::class)->validate($request->all());
        $result = app(IdempotencyService::class)->run($request->user()->id, $request->header('Idempotency-Key'), 'sale:create', $data, fn () => app(SaleService::class)->save($data, $request->user()->id));

        return response()->json($result, 201);
    }

    public function update(Request $request, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.manage');
        $data = app(SaleService::class)->validate($request->all());

        return app(IdempotencyService::class)->run($request->user()->id, $request->header('Idempotency-Key'), 'sale:'.$id.':update', $data, fn () => app(SaleService::class)->save($data, $request->user()->id, $id));
    }

    public function destroy(Request $request, int $id)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'sales.manage');

        return app(SaleService::class)->cancel($id, $request->user()->id);
    }
}
