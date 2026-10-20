<?php

namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequirementAllocationService
{
    public function allocate(int $eventId, int $requirementId, array $data, User $actor): array
    {
        return DB::transaction(function () use ($eventId, $requirementId, $data, $actor) {
            $event = InventoryData::activeEvent($eventId);
            InventoryData::assert(in_array($event->logistics_status, ['pending', 'reserved', 'preparing'], true), 'Evento no admite asignaciones.');
            $requirement = InventoryData::get('event_requirements', $requirementId, true);
            abort_unless($requirement->event_id === $eventId, 404);
            $existing = (float) DB::table('requirement_allocations')->where('event_requirement_id', $requirementId)->whereIn('status', ['reserved', 'picked', 'dispatched'])->sum('quantity');
            $requested = (float) collect($data['allocations'])->sum('quantity');
            InventoryData::assert($existing + $requested <= (float) $requirement->quantity_required, 'Asignación excede requerimiento.', 'REQUIREMENT_ALREADY_FULFILLED');
            $location = InventoryData::get('inventory_locations', $data['warehouse_location_id']);
            InventoryData::assert($location->type === 'warehouse' && $location->active, 'Almacén inválido.');
            // Common product locks serialize allocations competing for the same stock.
            DB::table('products')->whereIn('id', collect($data['allocations'])->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $ids = [];
            foreach ($data['allocations'] as $entry) {
                $this->validateCandidate($event, $requirement, $location->id, $entry['product_id'], (float) $entry['quantity'], $actor, $data['override_reason'] ?? null);
                $ids[] = InventoryData::insert('requirement_allocations', ['event_requirement_id' => $requirementId, 'product_id' => $entry['product_id'], 'quantity' => $entry['quantity'], 'warehouse_location_id' => $location->id, 'allocated_by' => $actor->id, 'allocated_at' => now(), 'notes' => $data['notes'] ?? null]);
            }
            $quantity = $existing + $requested;
            InventoryData::update('event_requirements', $requirementId, ['quantity_allocated' => $quantity, 'status' => $quantity === (float) $requirement->quantity_required ? 'allocated' : 'partially_covered']);
            InventoryData::update('events', $eventId, ['logistics_status' => 'preparing']);
            app(AuditService::class)->record($actor->id, 'allocation_created', 'event_requirement', $requirementId, $data);

            return ['allocations' => DB::table('requirement_allocations')->whereIn('id', $ids)->get()->toArray()];
        });
    }

    private function validateCandidate(object $event, object $requirement, int $location, int $productId, float $quantity, User $actor, ?string $reason, ?int $ignoreAllocation = null): void
    {
        $candidate = collect(app(ProductMatcherService::class)->candidates($event, $requirement, $location, true, $ignoreAllocation))->firstWhere('product_id', $productId);
        InventoryData::assert($candidate && $candidate['compatible'], 'Producto no compatible.', 'PRODUCT_NOT_COMPATIBLE');
        InventoryData::assert($quantity > 0 && $quantity <= $candidate['physical_stock'] - $candidate['reserved_stock'], 'Stock insuficiente.', 'INVENTORY_STOCK_INSUFFICIENT');
        if ($quantity > $candidate['available_stock']) {
            InventoryData::assert(app(InventoryAccess::class)->allows($actor, 'inventory.override_protected') && filled($reason), 'Producto protegido: requiere permiso y motivo.', 'ALLOCATION_OVERRIDE_REQUIRED');
            foreach ($candidate['protection']['rules'] as $ruleId) {
                DB::table('allocation_rule_overrides')->insert(['user_id' => $actor->id, 'rule_id' => $ruleId, 'product_id' => $productId, 'event_id' => $event->id, 'reason' => $reason, 'created_at' => now()]);
            }
            app(AuditService::class)->record($actor->id, 'protected_override', 'event', $event->id, ['product_id' => $productId, 'reason' => $reason]);
        }
    }

    public function replace(int $id, array $data, User $actor): array
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $original = InventoryData::get('requirement_allocations', $id);
            $requirement = InventoryData::get('event_requirements', $original->event_requirement_id);
            $event = InventoryData::activeEvent($requirement->event_id);
            $allocation = InventoryData::get('requirement_allocations', $id, true);
            InventoryData::assert($allocation->status === 'reserved' && ! DB::table('event_picking_items')->where('requirement_allocation_id', $id)->exists(), 'No se sustituye un surtido iniciado.');
            DB::table('products')->whereIn('id', [$allocation->product_id, $data['product_id']])->orderBy('id')->lockForUpdate()->get();
            $this->validateCandidate($event, $requirement, $allocation->warehouse_location_id, $data['product_id'], (float) $allocation->quantity, $actor, $data['override_reason'] ?? null, $id);
            InventoryData::update('requirement_allocations', $id, ['status' => 'replaced']);
            $new = InventoryData::insert('requirement_allocations', ['event_requirement_id' => $requirement->id, 'product_id' => $data['product_id'], 'quantity' => $allocation->quantity, 'warehouse_location_id' => $allocation->warehouse_location_id, 'allocated_by' => $actor->id, 'allocated_at' => now(), 'notes' => $data['reason']]);
            DB::table('allocation_replacements')->insert(['requirement_allocation_id' => $id, 'old_product_id' => $allocation->product_id, 'new_product_id' => $data['product_id'], 'quantity' => $allocation->quantity, 'reason' => $data['reason'], 'created_by' => $actor->id, 'created_at' => now()]);
            app(AuditService::class)->record($actor->id, 'allocation_replaced', 'requirement_allocation', $id, ['replacement_id' => $new, ...$data]);

            return ['allocation' => (array) InventoryData::get('requirement_allocations', $new)];
        });
    }
}
