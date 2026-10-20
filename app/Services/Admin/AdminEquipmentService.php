<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\ProductCatalogService;
use App\Services\Mobile\MobileContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminEquipmentService
{
    public function show(int $id): array
    {
        $equipment = (array) InventoryData::get('equipments', $id);
        $product = Product::where('legacy_equipment_id', $id)->first();
        $equipment['quantity'] = $product ? DB::table('inventory_balances')->where('product_id', $product->id)->sum('quantity') : 0;
        $equipment['product_id'] = $product?->id;
        $equipment['active'] = (bool) $equipment['active'];
        $equipment['image_url'] = app(AdminMediaService::class)->present($equipment['image_url']);

        return $equipment;
    }

    public function save(array $payload, int $actor, ?int $id = null): array
    {
        $data = Validator::make($payload, ['name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'quantity' => [$id ? 'sometimes' : 'required', 'integer', 'min:0'], 'category' => [$id ? 'sometimes' : 'required', 'in:detonators,cables,mortars,safety,tools'],
            'image_url' => ['nullable', 'url:http,https', 'max:4096'], 'active' => ['sometimes', 'boolean']])->validate();

        return DB::transaction(function () use ($data, $actor, $id) {
            app(AdminMediaService::class)->assertReference($data['image_url'] ?? null, 'image');
            if (array_key_exists('description', $data) && $data['description'] === null) {
                $data['description'] = '';
            }
            $old = $id ? (array) InventoryData::get('equipments', $id, true) : [];
            $fields = collect($data)->except('quantity')->all();
            if ($id) {
                InventoryData::update('equipments', $id, $fields);
            } else {
                $id = InventoryData::insert('equipments', $fields + ['description' => '', 'unit' => 'pz']);
            }
            $product = Product::where('legacy_equipment_id', $id)->lockForUpdate()->first();
            $role = DB::table('product_roles')->orderBy('id')->value('id');
            abort_unless($product || $role, 422, 'Configura primero los roles de producto.');
            $values = ['name' => $data['name'] ?? $old['name'], 'description' => $data['description'] ?? $old['description'] ?? '',
                'image_url' => array_key_exists('image_url', $data) ? $data['image_url'] : ($old['image_url'] ?? null), 'active' => $data['active'] ?? $old['active'] ?? true,
                'legacy_equipment_id' => $id, 'unit' => $old['unit'] ?? 'pz', 'product_role_id' => $product?->product_role_id ?? $role,
                'is_returnable' => true, 'is_consumable' => false, 'is_sellable' => false, 'tracks_inventory' => true];
            if (! $values['active'] && $product) {
                MobileContract::conflict(! DB::table('requirement_allocations')->where('product_id', $product->id)->whereIn('status', ['reserved', 'picked', 'dispatched'])->exists(), 'El equipo está asignado a una operación activa.', 'EQUIPMENT_IN_USE');
            }
            $saved = app(ProductCatalogService::class)->save($values, $actor, $product?->id);
            if (array_key_exists('quantity', $data)) {
                $current = DB::table('inventory_balances')->where('product_id', $saved['id'])->sum('quantity');
                if (MobileContract::milli($current) !== MobileContract::milli($data['quantity'])) {
                    MobileContract::conflict(! DB::table('inventory_movements')->where('product_id', $saved['id'])->exists(), 'Usa un ajuste auditado de inventario para modificar existencias de equipo.', 'EQUIPMENT_STOCK_IMMUTABLE');
                    if ($data['quantity'] > 0) {
                        $location = DB::table('inventory_locations')->where('code', 'EQUIPMENT-OPENING')->value('id') ?? InventoryData::insert('inventory_locations', ['name' => 'Equipo operativo — apertura', 'code' => 'EQUIPMENT-OPENING', 'type' => 'warehouse']);
                        app(InventoryMovementService::class)->record($saved['id'], (float) $data['quantity'], null, $location, 'adjustment_in', $actor, 'Existencia inicial de equipo', 'equipment', $id);
                    }
                }
            }
            app(AuditService::class)->record($actor, 'equipment_saved', 'equipment', $id, $data, $old);

            return $this->show($id);
        });
    }
}
