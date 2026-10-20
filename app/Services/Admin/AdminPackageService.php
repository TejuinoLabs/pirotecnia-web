<?php

namespace App\Services\Admin;

use App\Models\Package;
use App\Models\Product;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\PackageRequirementService;
use App\Services\Mobile\MobileContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminPackageService
{
    public function show(int $id): array
    {
        $model = Package::with(['materials', 'equipments'])->findOrFail($id);
        $package = $model->toArray();
        $package['video_url'] = app(AdminMediaService::class)->present($model->video_url);
        $package['products'] = DB::table('productables')->where('productable_type', Package::class)->where('productable_id', $id)->get(['product_id', 'quantity'])->map(fn ($row) => (array) $row)->all();
        $package['equipments'] = $model->equipments->map(fn ($row) => ['equipment_id' => $row->id, 'name' => $row->name, 'quantity' => $row->pivot->quantity, 'required' => (bool) $row->pivot->required])->all();

        return ['package' => $package, 'requirements' => DB::table('package_requirements')->where('package_id', $id)->where('active', true)->orderBy('sort_order')->get()->map(fn ($row) => (array) $row + ['constraints' => app(PackageRequirementService::class)->constraints('package_requirement', $row->id)])->all()];
    }

    public function save(array $payload, int $actor, ?int $id = null): array
    {
        $data = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'], 'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'string', 'max:100'], 'event_type_id' => ['nullable', 'integer', 'exists:event_types,id'], 'experience_level_id' => ['nullable', 'integer', 'exists:experience_levels,id'],
            'video_url' => ['nullable', 'url:http,https', 'max:4096'], 'products' => ['required', 'array', 'min:1'], 'products.*' => ['array:product_id,quantity'],
            'products.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'], 'products.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'equipments' => ['sometimes', 'array'], 'equipments.*' => ['array:equipment_id,quantity,required'], 'equipments.*.equipment_id' => ['required', 'integer', 'distinct', 'exists:equipments,id'],
            'equipments.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'], 'equipments.*.required' => ['required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($data, $actor, $id) {
            app(AdminMediaService::class)->assertReference($data['video_url'] ?? null, 'video');
            $model = $id ? Package::whereKey($id)->lockForUpdate()->firstOrFail() : new Package;
            if (! $id || $data['duration'] !== $model->duration) {
                Validator::make($data, ['duration' => ['numeric', 'gt:0']])->validate();
            }
            if ($id) {
                $advanced = DB::table('package_requirements')->where('package_id', $id)->where('active', true)->get();
                MobileContract::conflict(! $advanced->contains(fn ($r) => in_array($r->requirement_type, ['profile', 'specification'], true) || $r->allow_substitution || $r->reference_value !== null || $r->value_tolerance !== null || $r->selection_strategy !== null || collect(InventoryData::json($r->metadata))->except(['legacy_equipment_id', 'required'])->isNotEmpty() || app(PackageRequirementService::class)->constraints('package_requirement', $r->id)), 'El paquete tiene reglas avanzadas; edítalo en el panel.', 'ADVANCED_PACKAGE_REQUIREMENTS');
            }
            $equipmentProducts = [];
            foreach ($data['equipments'] ?? [] as $equipment) {
                $product = Product::where('legacy_equipment_id', $equipment['equipment_id'])->where('active', true)->where('is_returnable', true)->first();
                abort_unless($product, 422, 'El equipo #'.$equipment['equipment_id'].' no tiene producto operativo activo.');
                $equipmentProducts[$equipment['equipment_id']] = $product->id;
            }
            foreach ($data['products'] as $item) {
                abort_unless(Product::whereKey($item['product_id'])->where('active', true)->exists(), 422, 'Selecciona productos activos.');
                abort_if(in_array($item['product_id'], $equipmentProducts, true), 422, 'No dupliques un equipo como producto del paquete.');
            }
            $model->fill(collect($data)->except(['products', 'equipments'])->all() + ['status' => $model->status ?? 'active'])->save();
            DB::table('package_requirements')->where('package_id', $model->id)->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            DB::table('productables')->where('productable_type', Package::class)->where('productable_id', $model->id)->delete();
            $model->materials()->attach(collect($data['products'])->mapWithKeys(fn ($item) => [$item['product_id'] => ['quantity' => $item['quantity'], 'price' => 0]])->all());
            $model->equipments()->sync(collect($data['equipments'] ?? [])->mapWithKeys(fn ($item) => [$item['equipment_id'] => ['quantity' => $item['quantity'], 'required' => $item['required']]])->all());
            foreach ($data['products'] as $order => $item) {
                app(PackageRequirementService::class)->create($model->id, ['requirement_type' => 'specific_product', ...$item, 'sort_order' => $order, 'allow_substitution' => false], $actor);
            }
            foreach ($data['equipments'] ?? [] as $order => $item) {
                app(PackageRequirementService::class)->create($model->id, ['requirement_type' => 'equipment', 'product_id' => $equipmentProducts[$item['equipment_id']], 'quantity' => $item['quantity'], 'sort_order' => count($data['products']) + $order, 'allow_substitution' => false, 'metadata' => ['legacy_equipment_id' => $item['equipment_id'], 'required' => $item['required']]], $actor);
            }
            app(AuditService::class)->record($actor, 'admin_package_saved', 'package', $model->id, $data);

            return $this->show($model->id);
        });
    }
}
