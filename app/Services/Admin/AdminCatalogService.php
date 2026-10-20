<?php

namespace App\Services\Admin;

use App\Services\Inventory\AuditService;
use App\Services\Inventory\InventoryData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminCatalogService
{
    public const TABLES = ['client-types' => 'client_types', 'event-types' => 'event_types', 'experience-levels' => 'experience_levels', 'product-roles' => 'product_roles'];

    public function table(string $catalog): string
    {
        abort_unless(isset(self::TABLES[$catalog]), 404);

        return self::TABLES[$catalog];
    }

    public function show(string $catalog, int $id): array
    {
        $row = (array) InventoryData::get($this->table($catalog), $id);
        if (array_key_exists('active', $row)) {
            $row['active'] = (bool) $row['active'];
        }
        if (isset($row['image_url'])) {
            $row['image_url'] = app(AdminMediaService::class)->present($row['image_url']);
        }

        return $row;
    }

    public function save(string $catalog, array $payload, int $actor, ?int $id = null): array
    {
        abort_if($catalog === 'product-roles', 405);
        $table = $this->table($catalog);
        $data = Validator::make($payload, ['name' => [$id ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'], 'active' => ['sometimes', 'boolean'],
            ...($catalog === 'client-types' ? ['discount' => ['sometimes', 'numeric', 'between:0,100']] : []),
            ...($catalog === 'event-types' ? ['image_url' => ['nullable', 'url:http,https', 'max:4096']] : []),
        ])->validate();
        if (array_key_exists('description', $data) && $data['description'] === null) {
            $data['description'] = '';
        }

        return DB::transaction(function () use ($catalog, $table, $data, $actor, $id) {
            app(AdminMediaService::class)->assertReference($data['image_url'] ?? null, 'image');
            $old = $id ? (array) InventoryData::get($table, $id, true) : [];
            if ($id) {
                InventoryData::update($table, $id, $data);
            } else {
                $id = InventoryData::insert($table, $data + ['description' => '']);
            }
            app(AuditService::class)->record($actor, 'admin_catalog_saved', $table, $id, $data, $old);

            return $this->show($catalog, $id);
        });
    }
}
