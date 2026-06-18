<?php

namespace App\Http\Requests\Panel;

use App\Core\Data\Entities\Purchase;
use App\Models\Equipment;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => collect($item)->filter(fn ($value) => filled($value))->isNotEmpty())
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
            'status' => $this->input('status', 'confirmed'),
            'discount_enabled' => $this->boolean('discount_enabled'),
            'iva_enabled' => $this->boolean('iva_enabled'),
            'credit_limit_enabled' => $this->boolean('credit_limit_enabled'),
        ]);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:draft,confirmed'],
            'discount_enabled' => ['boolean'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'iva_enabled' => ['boolean'],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'credit_limit_enabled' => ['boolean'],
            'credit_limit_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:equipment,product,material'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            collect($this->input('items', []))->each(function ($item, $index) use ($validator) {
                $type = $item['type'] ?? null;
                $itemId = (int) ($item['item_id'] ?? 0);

                if ($type === 'equipment' && ! Equipment::whereKey($itemId)->exists()) {
                    $validator->errors()->add("items.$index.item_id", 'El equipo seleccionado no existe.');
                }

                if ($type === 'product' && ! Product::whereKey($itemId)->where('product_role_id', '!=', 2)->exists()) {
                    $validator->errors()->add("items.$index.item_id", 'El producto seleccionado no existe.');
                }

                if ($type === 'material' && ! Product::whereKey($itemId)->where('product_role_id', 2)->exists()) {
                    $validator->errors()->add("items.$index.item_id", 'El material seleccionado no existe.');
                }
            });
        });
    }

    public function attributes(): array
    {
        return [
            'date' => 'fecha',
            'supplier_name' => 'proveedor',
            'payment_method' => 'metodo de pago',
            'reference' => 'referencia',
            'observations' => 'observaciones',
            'status' => 'estatus',
            'discount_rate' => 'descuento',
            'iva_rate' => 'IVA',
            'credit_limit_amount' => 'limite de credito',
            'items' => 'conceptos',
            'items.*.type' => 'tipo de concepto',
            'items.*.item_id' => 'concepto',
            'items.*.quantity' => 'cantidad',
            'items.*.price' => 'costo unitario',
        ];
    }

    public function toEntity(int $userId): Purchase
    {
        $validated = $this->validated();

        $items = collect($validated['items']);

        return new Purchase([
            'user_id' => $userId,
            'date' => $validated['date'],
            'supplier_name' => $validated['supplier_name'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'observations' => $validated['observations'] ?? null,
            'status' => $validated['status'],
            'discount_enabled' => (bool) ($validated['discount_enabled'] ?? false),
            'discount_rate' => (float) ($validated['discount_rate'] ?? 0),
            'iva_enabled' => (bool) ($validated['iva_enabled'] ?? false),
            'iva_rate' => (float) ($validated['iva_rate'] ?? 16),
            'credit_limit_enabled' => (bool) ($validated['credit_limit_enabled'] ?? false),
            'credit_limit_amount' => $validated['credit_limit_amount'] ?? null,
            'products' => $items
                ->whereIn('type', ['product', 'material'])
                ->map(fn ($item) => [
                    'product_id' => (int) $item['item_id'],
                    'quantity' => (float) $item['quantity'],
                    'price' => (float) $item['price'],
                ])
                ->values(),
            'equipments' => $items
                ->where('type', 'equipment')
                ->map(fn ($item) => [
                    'equipment_id' => (int) $item['item_id'],
                    'quantity' => (float) $item['quantity'],
                    'price' => (float) $item['price'],
                ])
                ->values(),
        ]);
    }
}
