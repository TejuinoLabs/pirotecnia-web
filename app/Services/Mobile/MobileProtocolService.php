<?php

namespace App\Services\Mobile;

use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileProtocolService
{
    public const MILESTONES = ['arrived', 'installed', 'detonated', 'finished'];

    public function milestone(object $step): ?string
    {
        if ($step->milestone_code !== null) {
            return in_array($step->milestone_code, self::MILESTONES, true) ? $step->milestone_code : null;
        }

        return match (mb_strtolower(trim($step->title))) {
            'llegada al lugar', 'llegada al evento' => 'arrived',
            'instalación terminada', 'instalación completada' => 'installed',
            'detonación registrada', 'registro de detonación' => 'detonated',
            'conclusión de actividades', 'evento finalizado', 'finalización del evento' => 'finished',
            default => null,
        };
    }

    public function isReceiptReview(object $step): bool
    {
        return $step->validation_rule === 'receipt_review' || ($step->validation_rule === null && mb_strtolower(trim($step->title)) === 'recepción revisada');
    }

    public function validateNotes(?string $notes): void
    {
        if (trim($notes ?? '') === '') {
            throw ValidationException::withMessages(['notes' => 'Escribe comentarios sobre la actividad realizada.']);
        }
    }

    public function state(object $protocol): array
    {
        $steps = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('sort_order')->get();
        $records = DB::table('mobile_step_records')->where('event_id', $protocol->event_id)->get()->keyBy('step_id');
        $overrides = DB::table('mobile_step_overrides')->where('event_id', $protocol->event_id)->get()->keyBy('step_id');
        $milestones = [];
        $operational = 'not_started';
        foreach (self::MILESTONES as $code) {
            $matching = $steps->filter(fn ($step) => $this->milestone($step) === $code);
            $step = $matching->count() === 1 ? $matching->first() : null;
            $record = $step ? $records->get($step->id) : null;
            $override = $step ? $overrides->get($step->id) : null;
            $milestones[$code] = ['step_id' => $step?->id, 'status' => $record ? 'confirmed' : ($override ? 'overridden' : ($step ? 'pending' : 'not_configured')), 'occurred_at' => $record ? MobileContract::utc($record->occurred_at) : null, 'confirmed_at' => $record ? MobileContract::utc($record->created_at) : null, 'record_id' => $record?->id, 'override_id' => $override?->id];
            if ($record || $override) {
                $operational = $code;
            }
        }

        return ['operational_status' => $operational, 'milestones' => $milestones, 'protocol_status' => $protocol->status === 'closed' ? 'closed' : ($records->isNotEmpty() || $overrides->isNotEmpty() ? 'in_progress' : 'not_started')];
    }

    public function sync(object $protocol): void
    {
        $state = $this->state($protocol);
        InventoryData::update('mobile_event_protocols', $protocol->id, ['status' => $state['protocol_status']]);
        InventoryData::update('events', $protocol->event_id, ['operational_status' => $state['operational_status']]);
    }

    public function hasClosureBlockers(int $event): bool
    {
        return app(MobileMaterialsService::class)->hasPendingCustody($event)
            || DB::table('event_dispatches')->where('event_id', $event)->whereIn('status', ['draft', 'preparing', 'prepared', 'confirmed'])->exists()
            || DB::table('mobile_incidents')->where('event_id', $event)->whereIn('severity', ['high', 'critical'])->where('status', '!=', 'resolved')->exists();
    }
}
