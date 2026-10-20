<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccess;
use App\Services\Admin\AdminCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCatalogController extends Controller
{
    private function catalog(Request $request): string
    {
        app(AdminAccess::class)->authorize($request->user());

        return $request->route('admin_catalog') ?? 'client-types';
    }

    public function index(Request $request)
    {
        $catalog = $this->catalog($request);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return DB::table(app(AdminCatalogService::class)->table($catalog))->orderBy('id')->paginate(20)->through(fn ($row) => app(AdminCatalogService::class)->show($catalog, $row->id));
    }

    public function show(Request $request, int $id)
    {
        return app(AdminCatalogService::class)->show($this->catalog($request), $id);
    }

    public function store(Request $request)
    {
        return response()->json(app(AdminCatalogService::class)->save($this->catalog($request), $request->all(), $request->user()->id), 201);
    }

    public function update(Request $request, int $id)
    {
        return app(AdminCatalogService::class)->save($this->catalog($request), $request->all(), $request->user()->id, $id);
    }

    public function destroy(Request $request, int $id)
    {
        return app(AdminCatalogService::class)->save($this->catalog($request), ['active' => false], $request->user()->id, $id) + ['archived' => true];
    }
}
