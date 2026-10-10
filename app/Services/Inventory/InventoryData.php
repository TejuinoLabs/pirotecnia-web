<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

/** Persistence helpers for operational tables; legacy models remain intact. */
class InventoryData
{
    public static function get(string $table, int $id, bool $lock = false): object
    {
        $query = DB::table($table)->where('id', $id);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);

        return $record;
    }

    public static function insert(string $table, array $values): int
    {
        if ($table === 'inventory_locations' && ! isset($values['inventory_area'])) {
            $values['inventory_area'] = ($values['type'] ?? 'warehouse') === 'stand' ? 'stand' : (($values['type'] ?? 'warehouse') === 'warehouse' ? 'polvorin' : 'external');
        }

        return DB::table($table)->insertGetId(self::encode($values) + ['created_at' => now(), 'updated_at' => now()]);
    }

    public static function update(string $table, int $id, array $values): void
    {
        DB::table($table)->where('id', $id)->update(self::encode($values) + ['updated_at' => now()]);
    }

    public static function json(mixed $value): array
    {
        return is_array($value) ? $value : (json_decode($value ?? '[]', true) ?? []);
    }

    public static function encode(array $values): array
    {
        return array_map(fn ($value) => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value, $values);
    }

    public static function assert(bool $condition, string $message, string $code = 'INVALID_LOGISTICS_STATE'): void
    {
        if (! $condition) {
            throw new InventoryException($message, $code);
        }
    }
}
