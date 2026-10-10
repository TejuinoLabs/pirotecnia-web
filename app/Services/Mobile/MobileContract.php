<?php

namespace App\Services\Mobile;

use App\Services\Inventory\InventoryException;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class MobileContract
{
    public static function conflict(bool $condition, string $message, string $code = 'MOBILE_STATE_CONFLICT', array $errors = []): void
    {
        if (! $condition) {
            throw new InventoryException($message, $code, $errors, 409);
        }
    }

    public static function utc(mixed $value, ?string $timezone = null): ?string
    {
        return $value ? CarbonImmutable::parse($value, $timezone ?? config('app.timezone'))->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    public static function timestampRule(): array
    {
        return ['required', 'string', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/'];
    }

    public static function quantityRule(): array
    {
        return ['required', 'string', 'regex:/^\d{1,11}(?:\.\d{1,3})?$/'];
    }

    public static function milli(string|int|float $value): int
    {
        [$whole, $decimal] = array_pad(explode('.', (string) $value, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($decimal, 3, '0');
    }

    public static function quantity(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function page(LengthAwarePaginator $page, callable $map): array
    {
        return ['data' => $page->getCollection()->map($map)->values()->all(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]];
    }

    public static function dates(array $row): array
    {
        foreach ($row as $key => $value) {
            if (str_ends_with($key, '_at')) {
                $row[$key] = self::utc($value);
            }
            if (str_starts_with($key, 'quantity') || in_array($key, ['received_quantity', 'expected_quantity'], true)) {
                $row[$key] = self::quantity(self::milli($value));
            }
        }
        foreach (['attachment_ids', 'original_payload', 'allowed_capture_modes', 'allowed_evidence', 'dependencies'] as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                $row[$key] = json_decode($row[$key], true);
            }
        }

        return $row;
    }
}
