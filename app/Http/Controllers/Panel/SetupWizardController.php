<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\ProductCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetupWizardController extends Controller
{
    public function index(Request $request)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'system.setup');
        $progress = DB::table('application_setup_progress')->where('user_id', $request->user()->id)->first();
        $step = max(1, min(7, (int) $request->input('step', $progress->step ?? 1)));
        $titles = ['Catálogos básicos', 'Ubicaciones', 'Productos y paquetes', 'Personal y acceso móvil', 'Eventos y asignaciones', 'Existencias iniciales', 'Revisión'];
        $counts = collect(['categories', 'product_roles', 'event_types', 'client_types', 'experience_levels', 'products', 'packages', 'inventory_locations', 'employees', 'events', 'event_dispatches'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $locations = DB::table('inventory_locations')->where('active', true)->whereIn('type', ['warehouse', 'stand'])->orderBy('name')->get();
        $roles = DB::table('product_roles')->orderBy('name')->get();
        $categories = DB::table('categories')->where('active', true)->orderBy('name')->get();
        $users = DB::table('users')->orderBy('name')->get(['id', 'name', 'email']);
        $employees = DB::table('employees')->orderBy('name')->get(['id', 'name', 'user_id']);
        $events = DB::table('events')->orderByDesc('event_date')->paginate(15, ['id', 'client_name', 'event_date'], 'events_page');
        $products = Product::where('active', true)->where('tracks_inventory', true)->orderBy('name')->get();
        $positiveBalances = DB::table('inventory_balances')->where('quantity', '>', 0)->count();

        return view('panel.settings.setup', compact('progress', 'step', 'titles', 'counts', 'locations', 'roles', 'categories', 'users', 'employees', 'events', 'products', 'positiveBalances'));
    }

    public function store(Request $request)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'system.setup');
        $validated = $request->validate(['step' => 'required|integer|between:1,7', 'action' => 'required|in:continue,catalog,location,product,employee,assignment,count,finish']);
        $step = (int) $validated['step'];
        $actions = [1 => ['catalog', 'continue'], 2 => ['location', 'continue'], 3 => ['product', 'continue'], 4 => ['employee', 'continue'], 5 => ['assignment', 'continue'], 6 => ['count', 'continue'], 7 => ['finish']];
        $request->validate(['action' => [Rule::in($actions[$step])]]);
        $actor = $request->user()->id;
        DB::transaction(function () use ($request, $validated, $actor, $step) {
            switch ($validated['action']) {
                case 'catalog':
                    $data = $request->validate(['catalog' => 'required|in:categories,product_roles,event_types,client_types,experience_levels', 'name' => 'required|string|max:255', 'code' => 'required_if:catalog,categories|nullable|string|max:255', 'description' => 'nullable|string|max:2000']);
                    $values = ['name' => $data['name']];
                    if ($data['catalog'] === 'categories') {
                        $request->validate(['code' => 'unique:categories,code']);
                        $values += ['code' => $data['code'], 'active' => true];
                    } elseif ($data['catalog'] !== 'product_roles') {
                        $values['description'] = $data['description'] ?? '';
                    }
                    if (! DB::table($data['catalog'])->where('name', $data['name'])->exists()) {
                        InventoryData::insert($data['catalog'], $values);
                    }
                    break;
                case 'location':
                    $data = $request->validate(['name' => 'required|string|max:255', 'code' => 'required|string|max:255|unique:inventory_locations,code', 'inventory_area' => 'required|in:polvorin,stand,raw_material']);
                    InventoryData::insert('inventory_locations', $data + ['type' => $data['inventory_area'] === 'stand' ? 'stand' : 'warehouse', 'active' => true]);
                    break;
                case 'product':
                    app(ProductCatalogService::class)->save($request->only(['name', 'sku', 'unit', 'caliber', 'product_role_id', 'category_id', 'description', 'tracks_inventory', 'is_returnable', 'is_consumable', 'is_sellable', 'is_event_usable']), $actor);
                    break;
                case 'employee':
                    $data = $request->validate(['employee_id' => 'required|integer|exists:employees,id', 'user_id' => 'required|integer|exists:users,id']);
                    DB::table('users')->where('id', $data['user_id'])->lockForUpdate()->first();
                    $employee = DB::table('employees')->where('id', $data['employee_id'])->lockForUpdate()->first();
                    if ($employee->user_id && $employee->user_id !== (int) $data['user_id']) {
                        throw ValidationException::withMessages(['employee_id' => 'El empleado ya está vinculado a otra cuenta.']);
                    }
                    $request->validate(['user_id' => [Rule::unique('employees', 'user_id')->ignore($employee->id)]]);
                    DB::table('employees')->where('id', $employee->id)->update(['user_id' => $data['user_id'], 'updated_at' => now()]);
                    foreach (['mobile.receive', 'mobile.record', 'mobile.correct_own', 'mobile.incident', 'mobile.return', 'mobile.attach', 'mobile.close'] as $permission) {
                        DB::table('inventory_permissions')->insertOrIgnore(['user_id' => $data['user_id'], 'permission' => $permission]);
                    }
                    break;
                case 'assignment':
                    $data = $request->validate(['employee_id' => 'required|integer|exists:employees,id', 'event_id' => 'required|integer|exists:events,id']);
                    $request->validate(['employee_id' => [Rule::exists('employees', 'id')->whereNotNull('user_id')]]);
                    if (! DB::table('employee_event')->where($data)->exists()) {
                        DB::table('employee_event')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
                    }
                    break;
                case 'count':
                    $data = $request->validate(['product_id' => 'required|integer|exists:products,id', 'location_id' => 'required|integer|exists:inventory_locations,id', 'quantity' => 'required|numeric|min:0|decimal:0,3', 'notes' => 'required|string|max:2000']);
                    // Initial loading never overwrites stock recorded by another flow.
                    app(InventoryMovementService::class)->count($data + ['purpose' => 'initial', 'expected_quantity' => 0], $actor);
                    break;
                case 'continue':
                case 'finish':
                    if ($step === 6 || $validated['action'] === 'finish') {
                        $request->validate(['inventory_mode' => 'required|in:empty,loaded']);
                        if ($request->input('inventory_mode') === 'loaded') {
                            if (! DB::table('inventory_balances')->where('quantity', '>', 0)->exists()) {
                                throw ValidationException::withMessages(['inventory_mode' => 'Registra existencias reales o elige comenzar sin inventario.']);
                            }
                        }
                    }
                    break;
            }
            $previous = DB::table('application_setup_progress')->where('user_id', $actor)->first();
            DB::table('application_setup_progress')->updateOrInsert(['user_id' => $actor], [
                'step' => $validated['action'] === 'continue' ? min(7, $step + 1) : $step,
                'inventory_mode' => in_array($validated['action'], ['continue', 'finish'], true) ? ($request->input('inventory_mode') ?? $previous?->inventory_mode) : $previous?->inventory_mode,
                'completed_at' => $validated['action'] === 'finish' ? now() : $previous?->completed_at,
                'created_at' => $previous?->created_at ?? now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('settings.setup', ['step' => $validated['action'] === 'continue' ? min(7, $step + 1) : $step])->with('status', $validated['action'] === 'finish' ? 'Configuración inicial finalizada. Puedes volver al asistente cuando lo necesites.' : 'Guardado correctamente.');
    }
}
