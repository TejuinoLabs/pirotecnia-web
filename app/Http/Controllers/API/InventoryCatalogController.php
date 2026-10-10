<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\PackageRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryCatalogController extends Controller
{
    private const TABLES = ['categories' => 'categories', 'attributes' => 'attribute_definitions', 'effects' => 'effects', 'colors' => 'colors', 'locations' => 'inventory_locations', 'profiles' => 'product_profiles', 'inventory-protection-rules' => 'inventory_protection_rules', 'allocation-rules' => 'allocation_rules'];

    private function table(string $catalog): string
    {
        abort_unless(isset(self::TABLES[$catalog]), 404);

        return self::TABLES[$catalog];
    }

    public function index(Request $request)
    {
        $catalog = $request->route('catalog');

        return DB::table($this->table($catalog))->orderByDesc('id')->paginate(15)->withQueryString();
    }

    public function show(Request $request, int $id)
    {
        return $this->record($request->route('catalog'), $id);
    }

    private function record(string $catalog, int $id)
    {
        $record = (array) InventoryData::get($this->table($catalog), $id);
        if ($catalog === 'profiles') {
            $record['constraints'] = app(PackageRequirementService::class)->constraints('product_profile', $id);
        }

        return $record;
    }

    public function save(Request $request)
    {
        $id = $request->route('id') === null ? null : (int) $request->route('id');
        $catalog = $request->route('catalog');
        $table = $this->table($catalog);
        $rules = ['name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'], 'active' => ['boolean']];
        if (in_array($catalog, ['categories', 'effects', 'colors', 'attributes', 'locations'], true)) {
            $rules['code'] = [$id ? 'sometimes' : 'required', 'string', 'max:100', Rule::unique($table, 'code')->ignore($id)];
        }
        if ($catalog === 'attributes') {
            $rules += ['data_type' => [$id ? 'sometimes' : 'required', 'in:string,number,boolean,json'], 'unit' => ['nullable', 'string', 'max:100']];
        }
        if ($catalog === 'locations') {
            $rules += ['inventory_area' => ['sometimes', 'in:polvorin,stand,raw_material,external'], 'type' => [$id ? 'sometimes' : 'required', 'in:warehouse,stand,technician,event,damaged,quarantine,returns'], 'parent_id' => ['nullable', 'integer', 'exists:inventory_locations,id'], 'legacy_inventory_id' => ['nullable', 'integer', 'exists:inventories,id', Rule::unique($table, 'legacy_inventory_id')->ignore($id)]];
        }
        if ($catalog === 'profiles') {
            $rules += ['description' => ['nullable', 'string'], 'category_id' => [$id ? 'sometimes' : 'required', 'integer', 'exists:categories,id'], 'constraints' => ['array'], ...app(PackageRequirementService::class)->constraintRules()];
        }
        if ($catalog === 'inventory-protection-rules') {
            $rules += ['priority' => ['integer'], 'category_id' => ['nullable', 'integer', 'exists:categories,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id'], 'attribute_filters' => [$id ? 'sometimes' : 'required', 'array'], 'protected_quantity' => ['nullable', 'numeric', 'min:0'], 'protected_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'applicable_event_types' => ['nullable', 'array'], 'applicable_event_types.*' => ['integer', 'exists:event_types,id'], 'excluded_event_types' => ['nullable', 'array'], 'excluded_event_types.*' => ['integer', 'exists:event_types,id'], 'action' => [$id ? 'sometimes' : 'required', 'in:protect_stock,penalize,exclude,prefer'], 'message' => ['nullable', 'string', 'max:2000']];
        }
        if ($catalog === 'allocation-rules') {
            $rules += ['description' => ['nullable', 'string'], 'priority' => ['integer'], 'scope' => [$id ? 'sometimes' : 'required', 'in:global,category,event_type,package,product_profile'], 'condition_json' => [$id ? 'sometimes' : 'required', 'array:category_id,event_type_id,package_id,profile_id,product_id,attributes,min_available'], 'condition_json.category_id' => ['integer', 'exists:categories,id'], 'condition_json.event_type_id' => ['integer', 'exists:event_types,id'], 'condition_json.package_id' => ['integer', 'exists:packages,id'], 'condition_json.profile_id' => ['integer', 'exists:product_profiles,id'], 'condition_json.product_id' => ['integer', 'exists:products,id'], 'condition_json.attributes' => ['array'], 'condition_json.min_available' => ['numeric', 'min:0'], 'action_json' => [$id ? 'sometimes' : 'required', 'array:score_delta,exclude'], 'action_json.score_delta' => ['numeric'], 'action_json.exclude' => ['boolean']];
        }
        $data = $request->validate($rules);

        return DB::transaction(function () use ($table, $catalog, $id, $data, $request) {
            $old = $id ? (array) InventoryData::get($table, $id, true) : [];
            if ($catalog === 'locations') {
                $type = $data['type'] ?? $old['type'];
                $area = $data['inventory_area'] ?? $old['inventory_area'] ?? ($type === 'stand' ? 'stand' : ($type === 'warehouse' ? 'polvorin' : 'external'));
                InventoryData::assert($type === 'warehouse' ? in_array($area, ['polvorin', 'raw_material'], true) : $area === ($type === 'stand' ? 'stand' : 'external'), 'El área no corresponde al tipo de ubicación.');
                $data['inventory_area'] = $area;
            }
            if ($catalog === 'locations' && $id && ($old['adopted_at'] || DB::table('inventory_balances')->where('location_id', $id)->exists())) {
                InventoryData::assert(! array_key_exists('legacy_inventory_id', $data) || $data['legacy_inventory_id'] === $old['legacy_inventory_id'], 'No cambies el adaptador de un almacén con movimientos.');
                InventoryData::assert($data['inventory_area'] === $old['inventory_area'], 'No cambies el área de una ubicación con movimientos. Transfiere sus existencias a una ubicación nueva.');
                InventoryData::assert(! isset($data['type']) || $data['type'] === $old['type'], 'No cambies el tipo de una ubicación con movimientos.');
            }
            if ($catalog === 'attributes' && $id && isset($data['data_type'])) {
                InventoryData::assert($data['data_type'] === $old['data_type'] || ! DB::table('product_attribute_values')->where('attribute_definition_id', $id)->exists(), 'Atributo con valores: no se puede cambiar de tipo.');
            }
            $constraints = $data['constraints'] ?? null;
            unset($data['constraints']);
            if ($id) {
                InventoryData::update($table, $id, $data);
            } else {
                $id = InventoryData::insert($table, $data);
            }
            if ($constraints !== null) {
                DB::table('requirement_constraints')->where('owner_type', 'product_profile')->where('owner_id', $id)->delete();
                app(PackageRequirementService::class)->saveConstraints('product_profile', $id, $constraints);
            }
            app(AuditService::class)->record($request->user()->id, 'catalog_saved', $table, $id, $data, $old);

            return $this->record($catalog, $id);
        });
    }

    public function destroy(Request $request, int $id)
    {
        $catalog = $request->route('catalog');
        $table = $this->table($catalog);
        InventoryData::get($table, $id);
        InventoryData::update($table, $id, ['active' => false]);
        app(AuditService::class)->record($request->user()->id, 'catalog_deactivated', $table, $id);

        return ['deactivated' => true];
    }
}
