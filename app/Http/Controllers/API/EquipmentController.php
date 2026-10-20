<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccess;
use App\Services\Admin\AdminEquipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        app(AdminAccess::class)->authorize($request->user());
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return DB::table('equipments')->orderBy('id')->paginate(20)->through(fn ($row) => app(AdminEquipmentService::class)->show($row->id));
    }

    public function show(Request $request, int $id)
    {
        app(AdminAccess::class)->authorize($request->user());

        return app(AdminEquipmentService::class)->show($id);
    }

    public function store(Request $request)
    {
        app(AdminAccess::class)->authorize($request->user());

        return response()->json(app(AdminEquipmentService::class)->save($request->all(), $request->user()->id), 201);
    }

    public function update(Request $request, int $id)
    {
        app(AdminAccess::class)->authorize($request->user());

        return app(AdminEquipmentService::class)->save($request->all(), $request->user()->id, $id);
    }

    public function destroy(Request $request, int $id)
    {
        app(AdminAccess::class)->authorize($request->user());

        return app(AdminEquipmentService::class)->save(['active' => false], $request->user()->id, $id) + ['archived' => true];
    }
}
