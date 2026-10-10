<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PackageRequirementService
{
    public function create(int $packageId, array $payload, int $actor): array
    {
        InventoryData::get('packages', $packageId);
        $data = Validator::make($payload, [
            'requirement_type' => ['required', Rule::in(['specific_product', 'specification', 'profile', 'material', 'equipment'])],
            'product_id' => ['nullable', 'exists:products,id', 'required_if:requirement_type,specific_product,material,equipment'],
            'category_id' => ['nullable', 'exists:categories,id', 'required_if:requirement_type,specification'],
            'profile_id' => ['nullable', 'exists:product_profiles,id', 'required_if:requirement_type,profile'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'reference_value' => ['nullable', 'numeric', 'min:0'], 'value_tolerance' => ['nullable', 'numeric', 'min:0'],
            'allow_substitution' => ['boolean'], 'selection_strategy' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'], 'metadata' => ['array'],
            'constraints' => ['array'], ...$this->constraintRules(),
        ])->validate();

        return DB::transaction(function () use ($packageId, $data, $actor) {
            $constraints = $data['constraints'] ?? [];
            unset($data['constraints']);
            $id = InventoryData::insert('package_requirements', $data + ['package_id' => $packageId]);
            $this->saveConstraints('package_requirement', $id, $constraints);
            app(AuditService::class)->record($actor, 'requirement_created', 'package_requirement', $id, $data);

            return ['id' => $id] + $data + ['constraints' => $constraints];
        });
    }

    public function constraintRules(): array
    {
        return [
            'constraints.*' => ['array:attribute_code,operator,value,is_hard_constraint,weight'],
            'constraints.*.attribute_code' => ['required', 'string', 'max:100'],
            'constraints.*.operator' => ['required', Rule::in(['=', '!=', '>', '>=', '<', '<=', 'between', 'in', 'not_in', 'contains'])],
            'constraints.*.value' => ['present'], 'constraints.*.is_hard_constraint' => ['boolean'],
            'constraints.*.weight' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function saveConstraints(string $owner, int $id, array $constraints): void
    {
        foreach ($constraints as $constraint) {
            $operator = $constraint['operator'];
            if (in_array($operator, ['between', 'in', 'not_in'], true)) {
                InventoryData::assert(is_array($constraint['value']), 'El operador requiere una lista.');
                if ($operator === 'between') {
                    InventoryData::assert(count($constraint['value']) === 2, 'between requiere dos valores.');
                }
            }
            // JSON scalars must also be encoded (the shared helper encodes arrays only).
            $constraint['value'] = json_encode($constraint['value'], JSON_THROW_ON_ERROR);
            InventoryData::insert('requirement_constraints', $constraint + ['owner_type' => $owner, 'owner_id' => $id]);
        }
    }

    public function constraints(string $owner, int $id): array
    {
        return DB::table('requirement_constraints')->where('owner_type', $owner)->where('owner_id', $id)->get()->map(function ($row) {
            return ['attribute_code' => $row->attribute_code, 'operator' => $row->operator, 'value' => json_decode($row->value, true), 'is_hard_constraint' => (bool) $row->is_hard_constraint, 'weight' => $row->weight];
        })->all();
    }
}
