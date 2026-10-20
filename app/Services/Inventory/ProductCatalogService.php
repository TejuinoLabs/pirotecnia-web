<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductCatalogService
{
    public function save(array $payload, int $actor, ?int $id = null): array
    {
        $data = Validator::make($payload, [
            'name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($id)],
            'image_url' => ['nullable', 'url:http,https', 'max:4096'],
            'product_role_id' => [$id ? 'sometimes' : 'required', 'exists:product_roles,id'],
            'category_id' => ['nullable', 'exists:categories,id'], 'caliber' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string'],
            'legacy_equipment_id' => ['nullable', 'integer', 'exists:equipments,id', Rule::unique('products', 'legacy_equipment_id')->ignore($id)],
            'unit' => [$id ? 'sometimes' : 'required', 'string', 'max:100'],
            'active' => ['boolean'], 'tracks_inventory' => ['boolean'], 'is_consumable' => ['boolean'], 'is_returnable' => ['boolean'], 'is_sellable' => ['boolean'], 'is_event_usable' => ['boolean'],
            'reference_value' => ['nullable', 'numeric', 'min:0'], 'purchase_cost' => ['nullable', 'numeric', 'min:0'], 'sale_price' => ['nullable', 'numeric', 'min:0'], 'minimum_stock' => ['numeric', 'min:0'],
            'attributes' => ['array'], 'effects' => ['array'], 'effects.*' => ['integer', 'distinct', 'exists:effects,id'], 'colors' => ['array'], 'colors.*' => ['integer', 'distinct', 'exists:colors,id'],
            'attributes.shape' => ['nullable', 'string', 'max:255'], 'attributes.duration' => ['nullable', 'numeric', 'min:0'], 'attributes.shots' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($data, $id, $actor) {
            app(\App\Services\Admin\AdminMediaService::class)->assertReference($data['image_url'] ?? null, 'image');
            $product = $id ? Product::whereKey($id)->lockForUpdate()->firstOrFail() : new Product;
            $old = $product->toArray();
            $fields = collect($data)->except(['attributes', 'effects', 'colors'])->all();
            foreach (['shape', 'duration', 'shots'] as $technical) {
                if (array_key_exists($technical, $data['attributes'] ?? [])) {
                    $fields[$technical] = $data['attributes'][$technical];
                }
            }
            if (! $id) {
                $fields['description'] ??= '';
            }
            if ($fields['legacy_equipment_id'] ?? $product->legacy_equipment_id) {
                $fields['is_returnable'] = true;
                $fields['is_consumable'] = false;
            }
            if (isset($fields['description']) && $fields['description'] === null) {
                $fields['description'] = '';
            }
            $product->forceFill($fields)->save();
            if (array_key_exists('attributes', $data)) {
                foreach ($data['attributes'] as $code => $value) {
                    if (in_array($code, ['shape', 'duration', 'shots'], true)) {
                        DB::table('product_attribute_values')->where('product_id', $product->id)->whereIn('attribute_definition_id', DB::table('attribute_definitions')->where('code', $code)->select('id'))->delete();

                        continue;
                    }
                    $definition = DB::table('attribute_definitions')->where('code', $code)->where('active', true)->first();
                    InventoryData::assert($definition !== null, 'Atributo no definido: '.$code);
                    if ($value === null) {
                        DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_definition_id', $definition->id)->delete();

                        continue;
                    }
                    $valid = match ($definition->data_type) {
                        'number' => is_numeric($value), 'boolean' => is_bool($value), 'json' => is_array($value), default => is_string($value)
                    };
                    InventoryData::assert($valid, 'Tipo incorrecto para atributo '.$code);
                    $values = ['value_string' => null, 'value_number' => null, 'value_boolean' => null, 'value_json' => null];
                    $field = match ($definition->data_type) {
                        'number' => 'value_number', 'boolean' => 'value_boolean', 'json' => 'value_json', default => 'value_string'
                    };
                    $values[$field] = $value;
                    DB::table('product_attribute_values')->updateOrInsert(['product_id' => $product->id, 'attribute_definition_id' => $definition->id], InventoryData::encode($values + ['created_at' => now(), 'updated_at' => now()]));
                }
            }
            foreach (['effects' => 'effect_id', 'colors' => 'color_id'] as $name => $field) {
                if (! array_key_exists($name, $data)) {
                    continue;
                }
                DB::table('product_'.$name)->where('product_id', $product->id)->delete();
                foreach ($data[$name] as $value) {
                    DB::table('product_'.$name)->insert(['product_id' => $product->id, $field => $value]);
                }
            }
            app(AuditService::class)->record($actor, $id ? 'product_updated' : 'product_created', 'product', $product->id, $data, $old);

            return $this->show($product->id);
        });
    }

    public function show(int $id): array
    {
        $product = Product::findOrFail($id);
        $attributes = [];
        foreach (DB::table('product_attribute_values')->join('attribute_definitions', 'attribute_definitions.id', '=', 'attribute_definition_id')->where('product_id', $id)->pluck('code') as $code) {
            $attributes[$code] = app(ProductMatcherService::class)->attribute($product, $code);
        }

        $data = $product->toArray();
        $data['image_url'] = app(\App\Services\Admin\AdminMediaService::class)->present($product->image_url);

        return $data + ['attributes' => $attributes + ['shape' => $product->shape, 'duration' => $product->duration, 'shots' => $product->shots], 'effects' => app(ProductMatcherService::class)->attribute($product, 'effects'), 'colors' => app(ProductMatcherService::class)->attribute($product, 'colors')];
    }
}
