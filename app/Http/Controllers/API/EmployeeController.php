<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccess;
use App\Services\Admin\AdminEmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        app(AdminAccess::class)->authorize($request->user());
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return DB::table('employees')->orderBy('id')->paginate(20)->through(fn ($row) => app(AdminEmployeeService::class)->show($row->id));
    }

    public function show(Request $request, int $id)
    {
        app(AdminAccess::class)->authorize($request->user());

        return app(AdminEmployeeService::class)->show($id);
    }

    public function store(Request $request)
    {
        return response()->json(app(AdminEmployeeService::class)->save($request->all(), $request->user()), 201);
    }

    public function update(Request $request, int $id)
    {
        return app(AdminEmployeeService::class)->save($request->all(), $request->user(), $id);
    }

    public function destroy(Request $request, int $id)
    {
        return app(AdminEmployeeService::class)->archive($id, $request->user()) + ['archived' => true];
    }
}
