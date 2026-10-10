<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(int $actor, string $action, string $type, int $id, array $values = [], array $old = []): void
    {
        DB::table('inventory_audits')->insert(InventoryData::encode([
            'actor_id' => $actor, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_values' => $old, 'new_values' => $values, 'ip' => request()->ip(),
            'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]));
    }
}
