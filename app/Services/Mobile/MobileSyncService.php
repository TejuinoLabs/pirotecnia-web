<?php

namespace App\Services\Mobile;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileSyncService
{
    public function timeline(int $event, User $actor): array
    {
        app(TechnicianEventService::class)->authorize($actor, $event);
        $entries = collect();
        $append = function (string $type, object $row, string $title, mixed $occurred, mixed $recorded, int $actorId) use ($entries) {
            $entries->push(['id' => $type.':'.$row->id, 'entity_type' => $type, 'entity_id' => $row->id, 'title' => $title, 'occurred_at' => MobileContract::utc($occurred), 'recorded_at' => MobileContract::utc($recorded), 'actor_id' => $actorId]);
        };
        foreach (DB::table('mobile_step_records')->join('mobile_protocol_steps', 'mobile_protocol_steps.id', '=', 'mobile_step_records.step_id')->where('event_id', $event)->select('mobile_step_records.*', 'mobile_protocol_steps.title')->get() as $r) {
            $append('step_record', $r, $r->title, $r->occurred_at, $r->recorded_at, $r->recorded_by);
        }
        foreach (DB::table('mobile_record_corrections')->whereIn('record_id', DB::table('mobile_step_records')->where('event_id', $event)->select('id'))->get() as $r) {
            $append('correction', $r, 'Corrección: '.$r->reason, $r->occurred_at, $r->created_at, $r->actor_id);
        }
        foreach (DB::table('mobile_step_overrides')->where('event_id', $event)->get() as $r) {
            $append('override', $r, 'Excepción autorizada', $r->created_at, $r->created_at, $r->actor_id);
        }
        foreach (DB::table('mobile_incidents')->where('event_id', $event)->get() as $r) {
            $append('incident', $r, $r->description, $r->occurred_at, $r->recorded_at, $r->created_by);
        }
        foreach (DB::table('technician_receipts')->whereIn('event_dispatch_id', DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->select('id'))->get() as $r) {
            $append('receipt', $r, 'Recepción de despacho', $r->received_at, $r->created_at, $r->technician_id);
        }
        foreach (DB::table('mobile_return_declarations')->where('event_id', $event)->where('created_by', $actor->id)->get() as $r) {
            $append('return_declaration', $r, 'Devolución declarada', $r->occurred_at, $r->recorded_at, $r->created_by);
        }
        foreach (DB::table('event_returns')->where('event_id', $event)->where('returned_by', $actor->id)->where('status', 'confirmed')->get() as $r) {
            $append('warehouse_return', $r, 'Devolución confirmada por almacén', $r->returned_at, $r->updated_at, $r->received_by);
        }
        $protocol = DB::table('mobile_event_protocols')->where('event_id', $event)->first();
        if ($protocol?->closed_at) {
            $append('closure', $protocol, 'Bitácora cerrada', $protocol->closure_occurred_at ?? $protocol->closed_at, $protocol->closure_recorded_at ?? $protocol->updated_at, $protocol->closed_by);
        }
        $entries = $entries->sort(fn ($a, $b) => [$a['occurred_at'], $a['id']] <=> [$b['occurred_at'], $b['id']])->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return MobileContract::page(new LengthAwarePaginator($entries->forPage($page, 50)->values(), $entries->count(), 50, $page), fn ($r) => $r);
    }

    public function bootstrap(User $actor, ?string $cursor): array
    {
        $events = app(TechnicianEventService::class);
        if ($cursor) {
            try {
                $state = json_decode(Crypt::decryptString($cursor), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['cursor' => ['Cursor inválido.']]);
            }
            MobileContract::conflict(($state['actor'] ?? null) === $actor->id && CarbonImmutable::parse($state['expires'])->isFuture(), 'El cursor expiró o pertenece a otro usuario.', 'SYNC_CURSOR_INVALID');
        } else {
            $state = ['actor' => $actor->id, 'ids' => DB::table('events')->whereIn('id', $events->assignedIds($actor->id))->orderBy('id')->pluck('id')->all(), 'offset' => 0, 'server_time' => MobileContract::utc(now()), 'expires' => now()->addHour()->toIso8601String()];
        }
        // Full authorized snapshots deliberately avoid relying on client clocks or pivot timestamps.
        $assigned = DB::table('events')->whereIn('id', $events->assignedIds($actor->id))->pluck('id');
        $revoked = DB::table('mobile_sync_seen_events')->where('user_id', $actor->id)->whereNotIn('event_id', $assigned)->orderBy('event_id')->pluck('event_id')->all();
        $ids = array_slice($state['ids'], $state['offset'], config('mobile.page_size'));
        $state['offset'] += count($ids);
        $data = ['events' => [], 'dispatches' => [], 'protocols' => [], 'receipts' => [], 'step_records' => [], 'incidents' => [], 'return_declarations' => [], 'attachments' => [], 'revoked_event_ids' => $revoked, 'deleted_records' => []];
        foreach ($ids as $event) {
            if (! $assigned->contains($event)) {
                continue;
            }
            $data['events'][] = $events->detail($event, $actor);
            DB::table('mobile_sync_seen_events')->updateOrInsert(['user_id' => $actor->id, 'event_id' => $event], ['created_at' => now(), 'updated_at' => now()]);
            $data['protocols'][] = $events->protocol($event, $actor);
            $dispatchIds = DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->whereIn('status', ['confirmed', 'received', 'closed'])->orderBy('id')->pluck('id');
            foreach ($dispatchIds as $id) {
                $d = app(MobileDispatchService::class)->show($id, $actor);
                $data['dispatches'][] = $d;
                if ($d['receipt']) {
                    $data['receipts'][] = $d['receipt'];
                }
            }
            foreach (DB::table('mobile_step_records')->where('event_id', $event)->orderBy('id')->get() as $r) {
                $data['step_records'][] = $events->record($r, $actor);
            }
            foreach (DB::table('mobile_incidents')->where('event_id', $event)->orderBy('id')->get() as $r) {
                $data['incidents'][] = MobileContract::dates((array) $r);
            }
            foreach (DB::table('mobile_return_declarations')->whereIn('dispatch_id', $dispatchIds)->orderBy('id')->pluck('id') as $id) {
                $data['return_declarations'][] = app(MobileReturnService::class)->show($id);
            }
            foreach (DB::table('mobile_attachments')->where('event_id', $event)->get() as $file) {
                if ($file->expires_at <= now()->toDateTimeString()) {
                    $data['deleted_records'][] = ['entity_type' => 'attachment', 'entity_id' => $file->id];
                } else {
                    $data['attachments'][] = app(MobileAttachmentService::class)->present($file);
                }
            }
        }

        return ['data' => $data, 'meta' => ['server_time' => $state['server_time'], 'next_cursor' => $state['offset'] < count($state['ids']) ? Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR)) : null, 'schema_version' => 1, 'sync_mode' => 'full_authorized_snapshot']];
    }
}
