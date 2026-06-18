<?php

namespace App\Core\Data\Entities;

use Illuminate\Support\Collection;

class Purchase
{
    public $id;

    public $user_id;

    public $date;

    public $supplier_name;

    public $payment_method;

    public $reference;

    public $observations;

    public $status;

    public $discount_enabled;

    public $discount_rate;

    public $iva_enabled;

    public $iva_rate;

    public $credit_limit_enabled;

    public $credit_limit_amount;

    public Collection $products;

    public Collection $equipments;

    public function __construct(array $attributes = [])
    {
        $this->products = collect();
        $this->equipments = collect();

        // Asigna los atributos dinámicamente
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function fromPurchase($purchase)
    {
        return new Purchase([
            'id' => $purchase->id,
            'user_id' => $purchase->user_id,
            'date' => $purchase->date,
            'supplier_name' => $purchase->supplier_name,
            'payment_method' => $purchase->payment_method,
            'reference' => $purchase->reference,
            'observations' => $purchase->observations,
            'status' => $purchase->status ?? 'confirmed',
            'discount_enabled' => (bool) ($purchase->discount_enabled ?? false),
            'discount_rate' => (float) ($purchase->discount_rate ?? 0),
            'iva_enabled' => (bool) ($purchase->iva_enabled ?? false),
            'iva_rate' => (float) ($purchase->iva_rate ?? 16),
            'credit_limit_enabled' => (bool) ($purchase->credit_limit_enabled ?? false),
            'credit_limit_amount' => $purchase->credit_limit_amount,
            'products' => $purchase->relationLoaded('products')
                ? $purchase->products->map(fn ($product) => [
                    'product_id' => $product->id,
                    'quantity' => (float) ($product->pivot->quantity ?? 0),
                    'price' => (float) ($product->pivot->price ?? 0),
                ])
                : collect(),
            'equipments' => $purchase->relationLoaded('equipments')
                ? $purchase->equipments->map(fn ($equipment) => [
                    'equipment_id' => $equipment->id,
                    'quantity' => (float) ($equipment->pivot->quantity ?? 0),
                    'price' => (float) ($equipment->pivot->price ?? 0),
                ])
                : collect(),
        ]);
    }
}
