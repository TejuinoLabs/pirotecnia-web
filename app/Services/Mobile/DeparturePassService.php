<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeparturePassService
{
    public function dispatches(int $event, int $technician)
    {
        return DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $technician)->where('status', '!=', 'cancelled')->orderBy('id')->get();
    }

    public function blockers(int $event, int $technician): array
    {
        $reasons = [];
        $dispatches = $this->dispatches($event, $technician);
        if ($dispatches->isEmpty()) {
            $reasons[] = 'Falta preparar y entregar la carga del técnico.';
        }
        if (DB::table('event_requirements')->where('event_id', $event)->whereNotIn('status', ['dispatched', 'closed'])->exists()) {
            $reasons[] = 'Faltan requerimientos por despachar.';
        }
        if (DB::table('event_pickings')->where('event_id', $event)->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            $reasons[] = 'El almacén tiene preparación pendiente.';
        }
        if (DB::table('mobile_incidents')->where('event_id', $event)->where('status', '!=', 'resolved')->exists()) {
            $reasons[] = 'Hay incidencias abiertas que requieren revisión.';
        }
        foreach ($dispatches as $dispatch) {
            $receipt = DB::table('technician_receipts')->where('event_dispatch_id', $dispatch->id)->first();
            if ($dispatch->status !== 'received' || ! $receipt) {
                $reasons[] = 'Falta verificar la recepción del despacho #'.$dispatch->id.'.';

                continue;
            }
            if (! $dispatch->dispatched_by || $receipt->technician_id === $dispatch->dispatched_by) {
                $reasons[] = 'El despacho #'.$dispatch->id.' requiere firmas de dos personas distintas.';
            }
            if ($receipt->status !== 'received') {
                $reasons[] = 'El despacho #'.$dispatch->id.' fue recibido con diferencias; no admite pase ordinario.';
            }
            if (! app(DispatchIdentityService::class)->matches($dispatch)) {
                $reasons[] = 'La carga del despacho #'.$dispatch->id.' cambió; requiere reverificación.';
            }
            $lines = DB::table('event_dispatch_items')->where('event_dispatch_id', $dispatch->id)->get();
            $verified = DB::table('technician_receipt_items')->where('technician_receipt_id', $receipt->id)->get()->keyBy('dispatch_item_id');
            if ($lines->isEmpty() || $verified->count() !== $lines->count()) {
                $reasons[] = 'Faltan líneas verificadas en el despacho #'.$dispatch->id.'.';
            }
            foreach ($lines as $line) {
                $item = $verified->get($line->id);
                if (! $item || $item->status !== 'received' || MobileContract::milli($line->quantity) !== MobileContract::milli($item->received_quantity) || $line->serial_number !== $item->serial_number) {
                    $reasons[] = 'Falta verificar cantidad o serie de la línea #'.$line->id.'.';
                }
            }
            if (DB::table('mobile_return_declarations')->where('dispatch_id', $dispatch->id)->exists() || DB::table('event_returns')->where('event_dispatch_id', $dispatch->id)->exists()) {
                $reasons[] = 'La carga del despacho #'.$dispatch->id.' ya está en proceso de devolución.';
            }
        }

        return array_values(array_unique($reasons));
    }

    public function show(int $event, User $actor): array
    {
        $row = app(TechnicianEventService::class)->authorize($actor, $event);
        $pass = DB::table('event_departure_passes')->where('event_id', $event)->where('technician_id', $actor->id)->orderByDesc('version')->first();
        $reasons = $this->blockers($event, $actor->id);
        $status = $reasons ? 'blocked' : 'not_issued';
        if ($pass) {
            $snapshots = InventoryData::json($pass->dispatches);
            $current = $this->dispatches($event, $actor->id);
            $identity = app(DispatchIdentityService::class);
            if (count($snapshots) !== $current->count() || collect($snapshots)->contains(function ($snapshot) use ($current, $identity) {
                $dispatch = $current->firstWhere('id', $snapshot['id']);

                return ! $dispatch || $dispatch->version !== $snapshot['version'] || ! hash_equals($snapshot['content_hash'], $identity->fingerprint($dispatch->id));
            })) {
                $reasons[] = 'El pase no corresponde a la versión vigente de todos los despachos.';
            }
            $status = $pass->revoked_at ? 'revoked' : (CarbonImmutable::parse($pass->expires_at)->isPast() ? 'expired' : ($reasons ? 'blocked' : 'valid'));
            if ($pass->revoked_at) {
                $reasons[] = $pass->revocation_reason;
            }
            if ($status === 'expired') {
                $reasons[] = 'El pase venció; solicita al almacén una nueva emisión.';
            }
        }
        $issuer = $pass ? DB::table('users')->where('id', $pass->issued_by)->first(['id', 'name']) : null;

        return ['data' => ['id' => $pass?->id, 'event_id' => $event, 'event_title' => 'Evento de '.$row->client_name, 'status' => $status,
            'folio' => $pass?->folio, 'version' => $pass?->version, 'issued_at' => MobileContract::utc($pass?->issued_at),
            'expires_at' => MobileContract::utc($pass?->expires_at), 'revoked_at' => MobileContract::utc($pass?->revoked_at),
            'technician' => ['id' => $actor->id, 'name' => $actor->name], 'issued_by' => $issuer ? (array) $issuer : null,
            'dispatches' => $pass ? array_map(fn ($snapshot) => ['id' => $snapshot['id'], 'version' => $snapshot['version']], InventoryData::json($pass->dispatches)) : [],
            'blocking_reasons' => array_values(array_unique($reasons)), 'server_timestamp' => MobileContract::utc(now())]];
    }

    public function issue(int $event, array $data, User $actor): array
    {
        app(InventoryAccess::class)->authorize($actor, 'inventory.departure.issue');

        return DB::transaction(function () use ($event, $data, $actor) {
            InventoryData::get('events', $event, true);
            $technician = User::findOrFail($data['technician_id']);
            app(TechnicianEventService::class)->authorize($technician, $event);
            abort_if($technician->id === $actor->id, 403, 'El técnico no puede emitir su propio pase.');
            $dispatches = $this->dispatches($event, $technician->id);
            $expected = collect($data['dispatches'])->keyBy('id');
            MobileContract::conflict($expected->count() === $dispatches->count() && $dispatches->every(fn ($d) => $expected->has($d->id) && $expected[$d->id]['version'] === $d->version), 'La lista o versión de despachos cambió.', 'DISPATCH_VERSION_CONFLICT');
            $reasons = $this->blockers($event, $technician->id);
            MobileContract::conflict(! $reasons, 'No se puede emitir el pase de salida.', 'DEPARTURE_PASS_BLOCKED', ['blocking_reasons' => $reasons]);
            MobileContract::conflict(CarbonImmutable::parse($data['expires_at'])->isFuture(), 'La vigencia debe ser posterior a la emisión.', 'DEPARTURE_PASS_EXPIRY_INVALID');
            $this->invalidate($event, $actor->id, 'Pase sustituido por una nueva emisión.', $technician->id);
            $version = (int) DB::table('event_departure_passes')->where('event_id', $event)->where('technician_id', $technician->id)->max('version') + 1;
            $folio = sprintf('PSR-%s-%06d-%06d-%03d', now()->format('Y'), $event, $technician->id, $version);
            $id = InventoryData::insert('event_departure_passes', ['event_id' => $event, 'technician_id' => $technician->id, 'folio' => $folio,
                'version' => $version, 'issued_by' => $actor->id, 'issued_at' => now(), 'expires_at' => CarbonImmutable::parse($data['expires_at'])->setTimezone(config('app.timezone'))->toDateTimeString(),
                'dispatches' => $dispatches->map(fn ($d) => ['id' => $d->id, 'version' => $d->version, 'content_hash' => app(DispatchIdentityService::class)->fingerprint($d->id)])->all()]);
            app(AuditService::class)->record($actor->id, 'departure_pass_issued', 'event_departure_pass', $id, ['event_id' => $event, 'folio' => $folio, 'version' => $version]);

            return $this->show($event, $technician);
        });
    }

    public function revoke(int $event, int $id, array $data, User $actor): array
    {
        app(InventoryAccess::class)->authorize($actor, 'inventory.departure.revoke');

        return DB::transaction(function () use ($event, $id, $data, $actor) {
            InventoryData::get('events', $event, true);
            $pass = InventoryData::get('event_departure_passes', $id, true);
            abort_unless($pass->event_id === $event, 404);
            abort_if($pass->technician_id === $actor->id, 403);
            MobileContract::conflict($pass->version === (int) $data['expected_version'], 'El pase cambió.', 'DEPARTURE_PASS_VERSION_CONFLICT');
            if (! $pass->revoked_at) {
                $this->revokeRow($pass, $actor->id, $data['reason']);
            }

            return ['data' => MobileContract::dates((array) InventoryData::get('event_departure_passes', $id))];
        });
    }

    public function invalidate(int $event, int $actor, string $reason, ?int $technician = null): void
    {
        foreach (DB::table('event_departure_passes')->where('event_id', $event)->whereNull('revoked_at')->when($technician, fn ($q) => $q->where('technician_id', $technician))->lockForUpdate()->get() as $pass) {
            $this->revokeRow($pass, $actor, $reason);
        }
    }

    private function revokeRow(object $pass, int $actor, string $reason): void
    {
        InventoryData::update('event_departure_passes', $pass->id, ['revoked_by' => $actor, 'revoked_at' => now(), 'revocation_reason' => $reason]);
        app(AuditService::class)->record($actor, 'departure_pass_revoked', 'event_departure_pass', $pass->id, ['event_id' => $pass->event_id, 'reason' => $reason]);
    }
}
