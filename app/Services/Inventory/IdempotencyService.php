<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    public function run(int $actor, ?string $key, string $operation, array $payload, callable $callback): array
    {
        if ($key === null || $key === '') {
            return $callback();
        }
        InventoryData::assert(strlen($key) <= 100, 'Idempotency-Key excede 100 caracteres.');

        return DB::transaction(function () use ($actor, $key, $operation, $payload, $callback) {
            // Serialize retries per actor, including creation of a previously absent key.
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = DB::table('inventory_idempotency_keys')->where('user_id', $actor)->where('key', $key)->where('operation', $operation)->first();
            if ($existing) {
                InventoryData::assert($existing->request_hash === $hash, 'La clave ya fue usada con otro payload.', 'IDEMPOTENCY_KEY_CONFLICT');

                return InventoryData::json($existing->response);
            }
            $id = InventoryData::insert('inventory_idempotency_keys', ['user_id' => $actor, 'key' => $key, 'operation' => $operation, 'request_hash' => $hash]);
            $response = $callback();
            InventoryData::update('inventory_idempotency_keys', $id, ['response' => $response]);

            return $response;
        });
    }
}
