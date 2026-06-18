<?php

namespace App\Core\Data\Entities;

use Illuminate\Support\Collection;

class Product
{
    public $id;

    public $product_role_id;

    public $name;

    public $description;

    public $unit;

    public $duration;

    public $shots;

    public $caliber;

    public $shape;

    public $quantity;

    public $price;

    public Collection $inventory;

    public function __construct(array $attributes = [])
    {
        // Asigna los atributos dinámicamente
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function __toString()
    {
        return json_encode($this);
    }

    public static function fromProduct($product, $from = 'package')
    {
        return new Product([
            'id' => $product->id,
            'product_role_id' => $product->product_role_id,
            'name' => $product->name,
            'description' => $product->description,
            'unit' => $product->unit,
            'duration' => $product->duration,
            'shots' => $product->shots,
            'caliber' => $product->caliber,
            'shape' => $product->shape,
            'quantity' => self::extractQuantityFrom($product, $from),
            'price' => self::extractPriceFrom($product, $from),
        ]);
    }

    private static function extractPriceFrom($product, $from)
    {
        if (($from === 'event' || $from === 'package') && isset($product->pivot)) {
            return (float) ($product->pivot->price ?? 0);
        }

        return $product->inventories->first()?->pivot?->price ?? 0;
    }

    private static function extractQuantityFrom($product, $from)
    {
        if (($from === 'package' || $from === 'event') && isset($product->pivot)) {
            return $product->pivot->quantity;
        }

        return $product->inventories->first()?->pivot?->quantity ?? 0;
    }
}
