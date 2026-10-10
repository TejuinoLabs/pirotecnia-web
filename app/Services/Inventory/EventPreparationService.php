<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class EventPreparationService
{
    public function show(int $eventId): array
    {
        $event = InventoryData::get('events', $eventId);
        $requirements = app(EventRequirementService::class)->list($eventId);
        $required = collect($requirements)->sum('quantity_required');
        $allocated = collect($requirements)->sum('quantity_allocated');

        return ['event' => (array) $event, 'preparation' => DB::table('event_preparations')->where('event_id', $eventId)->first(),
            'percent_completed' => $required > 0 ? round($allocated / $required * 100, 1) : 0,
            'requirements' => $requirements, 'coverage' => app(EventCoverageService::class)->coverage($eventId),
            'allocations' => DB::table('requirement_allocations')->whereIn('event_requirement_id', collect($requirements)->pluck('id'))->get()->toArray(),
            'pickings' => DB::table('event_pickings')->where('event_id', $eventId)->get()->toArray(),
            'dispatches' => DB::table('event_dispatches')->where('event_id', $eventId)->get()->toArray(),
            'returns' => DB::table('event_returns')->where('event_id', $eventId)->get()->toArray()];
    }

    public function transition(int $eventId, bool $complete, int $actor): array
    {
        return DB::transaction(function () use ($eventId, $complete, $actor) {
            $event = InventoryData::get('events', $eventId, true);
            InventoryData::assert(in_array($event->logistics_status, ['pending', 'reserved', 'preparing'], true), 'Estado logístico inválido.');
            if ($complete) {
                InventoryData::assert(DB::table('event_requirements')->where('event_id', $eventId)->exists(), 'Evento sin requerimientos.');
                InventoryData::assert(! DB::table('event_requirements')->where('event_id', $eventId)->whereColumn('quantity_allocated', '<', 'quantity_required')->exists(), 'Faltan asignaciones.');
                $hasPickings = DB::table('event_pickings')->where('event_id', $eventId)->exists();
                InventoryData::assert($hasPickings && ! DB::table('event_pickings')->where('event_id', $eventId)->where('status', '!=', 'completed')->exists(), 'Picking incompleto.');
            }
            DB::table('event_preparations')->updateOrInsert(['event_id' => $eventId], ['status' => $complete ? 'ready' : 'in_progress', 'responsible_id' => $actor, 'started_at' => now(), 'completed_at' => $complete ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
            InventoryData::update('events', $eventId, ['logistics_status' => $complete ? 'ready' : 'preparing']);
            app(AuditService::class)->record($actor, $complete ? 'preparation_completed' : 'preparation_started', 'event', $eventId);

            return $this->show($eventId);
        });
    }
}
