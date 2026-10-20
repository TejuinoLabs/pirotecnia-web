<?php

namespace App\Services\Inventory;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventRequirementService
{
    public function snapshot(Event $event, int $actor): array
    {
        return DB::transaction(function () use ($event, $actor) {
            InventoryData::get('events', $event->id, true);
            if (DB::table('event_requirements')->where('event_id', $event->id)->exists()) {
                return $this->list($event->id);
            }
            InventoryData::assert(! $event->products()->wherePivot('check_almacen', true)->exists()
                && ! $event->equipments()->wherePivot('check_almacen', true)->exists(), 'Evento con salida anterior: conciliar antes de adoptar la logística nueva.');
            $requirements = app(PackageRequirementService::class);
            $event->load(['packages', 'products', 'equipments']);
            $coveredLegacy = [];
            foreach ($event->packages as $package) {
                $quantity = (float) ($package->pivot->quantity ?? 1);
                $rows = DB::table('package_requirements')->where('package_id', $package->id)->where('active', true)->orderBy('sort_order')->get();
                foreach ($rows as $row) {
                    if ((InventoryData::json($row->metadata)['required'] ?? true) === false) {
                        if ($row->product_id) {
                            $coveredLegacy[] = $row->product_id;
                        }

                        continue;
                    }
                    $constraints = $requirements->constraints('package_requirement', $row->id);
                    $categoryId = $row->category_id;
                    if ($row->profile_id) {
                        $profile = InventoryData::get('product_profiles', $row->profile_id);
                        InventoryData::assert((bool) $profile->active, 'Perfil inactivo.');
                        $categoryId ??= $profile->category_id;
                        $constraints = array_merge($requirements->constraints('product_profile', $row->profile_id), $constraints);
                    }
                    $this->insert($event->id, [
                        'source_package_requirement_id' => $row->id, 'requirement_type' => $row->requirement_type,
                        'product_id' => $row->product_id, 'category_id' => $categoryId, 'profile_id' => $row->profile_id,
                        'quantity_required' => (float) $row->quantity * $quantity, 'reference_value' => $row->reference_value,
                        'value_tolerance' => $row->value_tolerance, 'allow_substitution' => $row->allow_substitution,
                        'selection_strategy' => $row->selection_strategy, 'metadata' => InventoryData::json($row->metadata),
                    ], $constraints, ['package_id' => $package->id, 'package_name' => $package->name]);
                    if ($row->product_id) {
                        $coveredLegacy[] = $row->product_id;
                    }
                }
            }
            // Preserve custom and old package products; no SKU is inferred from names.
            foreach ($event->products as $product) {
                if (in_array($product->id, $coveredLegacy, true)) {
                    continue;
                }
                $this->insert($event->id, [
                    'requirement_type' => 'specific_product', 'product_id' => $product->id,
                    'quantity_required' => (float) $product->pivot->quantity, 'allow_substitution' => false,
                ], [], ['product_name' => $product->name, 'legacy_price' => $product->pivot->price]);
            }
            foreach ($event->equipments as $equipment) {
                $product = \App\Models\Product::where('legacy_equipment_id', $equipment->id)->first();
                if ($product && in_array($product->id, $coveredLegacy, true)) {
                    continue;
                }
                $this->insert($event->id, [
                    'requirement_type' => 'equipment', 'product_id' => $product?->id,
                    'quantity_required' => (float) $equipment->pivot->quantity, 'allow_substitution' => false,
                    'metadata' => ['legacy_equipment_id' => $equipment->id],
                ], [['attribute_code' => 'legacy_equipment_id', 'operator' => '=', 'value' => $equipment->id, 'is_hard_constraint' => true]], ['product_name' => $equipment->name]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('mobile_event_protocols')) {
                app(\App\Services\Mobile\TechnicianEventService::class)->snapshot($event->id);
            }
            app(AuditService::class)->record($actor, 'requirements_snapshotted', 'event', $event->id);

            return $this->list($event->id);
        });
    }

    private function insert(int $eventId, array $data, array $constraints, array $source): void
    {
        if (! empty($data['category_id'])) {
            $source['category_name'] = InventoryData::get('categories', $data['category_id'])->name;
        }
        if (! empty($data['product_id'])) {
            $source['product_name'] = InventoryData::get('products', $data['product_id'])->name;
        }
        if (! empty($data['profile_id'])) {
            $source['profile_name'] = InventoryData::get('product_profiles', $data['profile_id'])->name;
        }
        $id = InventoryData::insert('event_requirements', $data + ['event_id' => $eventId, 'snapshot' => $data + $source + ['constraints' => $constraints]]);
        app(PackageRequirementService::class)->saveConstraints('event_requirement', $id, $constraints);
        InventoryData::insert('inventory_reservations', ['reservation_type' => 'requirement', 'event_id' => $eventId, 'event_requirement_id' => $id, 'quantity' => $data['quantity_required']]);
    }

    public function list(int $eventId): array
    {
        return DB::table('event_requirements')->where('event_id', $eventId)->get()->map(function ($row) {
            $result = (array) $row;
            $result['snapshot'] = InventoryData::json($row->snapshot);
            $result['constraints'] = app(PackageRequirementService::class)->constraints('event_requirement', $row->id);

            return $result;
        })->all();
    }
}
