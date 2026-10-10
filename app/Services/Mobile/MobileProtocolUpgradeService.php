<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;

class MobileProtocolUpgradeService
{
    public function plan(int $event): array
    {
        InventoryData::get('events', $event);
        $protocol = DB::table('mobile_event_protocols')->where('event_id', $event)->first();
        $steps = $protocol ? DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('sort_order')->get() : collect();
        $rules = app(MobileProtocolService::class);
        $mapped = $steps->groupBy(fn ($step) => $rules->milestone($step) ?? 'additional');
        $coded = $steps->pluck('milestone_code')->filter()->all();
        $missing = array_values(array_diff(MobileProtocolService::MILESTONES, $coded));
        $blocker = null;
        if ($protocol && ($protocol->status === 'closed' || DB::table('mobile_step_records')->whereIn('step_id', ($mapped->get('finished') ?? collect())->pluck('id'))->exists() || DB::table('mobile_step_overrides')->whereIn('step_id', ($mapped->get('finished') ?? collect())->pluck('id'))->exists())) {
            $blocker = 'El protocolo ya finalizó o tiene una conclusión registrada; conserva su historial.';
        } elseif ($protocol && (($mapped->get('arrived')?->count() ?? 0) !== 1 || ($mapped->get('finished')?->count() ?? 0) !== 1 || $mapped->except('additional')->contains(fn ($group) => $group->count() > 1))) {
            $blocker = 'La plantilla no permite identificar cada hito de forma única. Requiere revisión administrativa.';
        }

        $recognized = $steps->map(fn ($step) => $rules->milestone($step))->filter()->values()->all();
        $ordered = array_values(array_intersect(MobileProtocolService::MILESTONES, $recognized));
        if ($protocol && ($recognized !== $ordered || ($mapped->has('detonated') && ! $mapped->has('installed')))) {
            $blocker = 'Los hitos existentes no permiten insertar los pasos en una secuencia coherente. Requiere revisión administrativa.';
        }

        return ['event_id' => $event, 'protocol_id' => $protocol?->id, 'current_version' => $protocol?->version ?? 0, 'target_version' => $protocol && ! $missing ? $protocol->version : max(config('mobile.protocol.version'), ($protocol?->version ?? 0) + 1), 'needs_upgrade' => ! $protocol || count($missing) > 0, 'missing_codes' => $missing, 'blocker' => $blocker, 'steps' => $steps->map(fn ($step) => ['id' => $step->id, 'title' => $step->title, 'order' => $step->sort_order, 'milestone_code' => $step->milestone_code])->all()];
    }

    public function apply(int $event, User $actor, int $expectedVersion, string $reason): array
    {
        app(InventoryAccess::class)->authorize($actor, 'mobile.protocol.manage');
        InventoryData::assert(trim($reason) !== '', 'Escribe el motivo de la actualización.');

        return DB::transaction(function () use ($event, $actor, $expectedVersion, $reason) {
            InventoryData::get('events', $event, true);
            $plan = $this->plan($event);
            MobileContract::conflict($plan['current_version'] === $expectedVersion, 'El protocolo cambió; revisa el plan actualizado.', 'PROTOCOL_VERSION_CONFLICT');
            if (! $plan['needs_upgrade']) {
                return $plan + ['updated' => false];
            }
            MobileContract::conflict($plan['blocker'] === null, $plan['blocker'] ?? '', 'PROTOCOL_UPGRADE_BLOCKED');
            if (! $plan['protocol_id']) {
                $protocol = app(TechnicianEventService::class)->snapshot($event);
            } else {
                $protocol = InventoryData::get('mobile_event_protocols', $plan['protocol_id'], true);
                $steps = DB::table('mobile_protocol_steps')->where('protocol_id', $protocol->id)->orderBy('sort_order')->get();
                $rules = app(MobileProtocolService::class);
                $finish = $steps->first(fn ($step) => $rules->milestone($step) === 'finished');
                $insertionOrder = $finish->sort_order;
                $previousDependencies = InventoryData::json($finish->dependencies);
                $definitions = collect(config('mobile.protocol.steps'))->keyBy('milestone_code');
                // Move to temporary positions first to preserve every existing ID and unique order.
                $offset = $steps->max('sort_order') + 10;
                foreach ($steps as $step) {
                    InventoryData::update('mobile_protocol_steps', $step->id, ['sort_order' => $step->sort_order + $offset]);
                }
                $newSteps = [];
                foreach (['installed', 'detonated'] as $code) {
                    $existing = $steps->first(fn ($step) => $rules->milestone($step) === $code);
                    if ($existing) {
                        $previousDependencies = [$existing->id];

                        continue;
                    }
                    $definition = $definitions[$code];
                    $newSteps[] = InventoryData::insert('mobile_protocol_steps', ['protocol_id' => $protocol->id, 'sort_order' => $insertionOrder + count($newSteps), 'milestone_code' => $code, 'requires_notes' => true, 'title' => $definition['title'], 'instructions' => $definition['instructions'], 'required' => true, 'capture_mode' => 'automatic', 'allowed_capture_modes' => ['automatic', 'manual'], 'allow_notes' => true, 'allowed_evidence' => config('mobile.attachment_mimes'), 'dependencies' => $previousDependencies]);
                    $previousDependencies = [end($newSteps)];
                }
                foreach ($steps as $step) {
                    $code = $rules->milestone($step);
                    $values = ['sort_order' => $step->sort_order + ($step->sort_order >= $insertionOrder ? count($newSteps) : 0)];
                    if ($code) {
                        $values += ['milestone_code' => $code, 'requires_notes' => true, 'allow_notes' => true];
                    }
                    if ($rules->isReceiptReview($step)) {
                        $values['validation_rule'] = 'receipt_review';
                    }
                    if ($step->id === $finish->id && $newSteps) {
                        $values['dependencies'] = array_values(array_unique([...InventoryData::json($finish->dependencies), ...$previousDependencies]));
                    }
                    InventoryData::update('mobile_protocol_steps', $step->id, $values);
                }
                InventoryData::update('mobile_event_protocols', $protocol->id, ['version' => $plan['target_version']]);
            }
            app(MobileProtocolService::class)->sync($protocol);
            $after = $this->plan($event);
            app(AuditService::class)->record($actor->id, 'mobile_protocol_upgraded', 'mobile_event_protocol', $protocol->id, $after + ['reason' => trim($reason)], $plan);

            return $after + ['updated' => true];
        });
    }
}
