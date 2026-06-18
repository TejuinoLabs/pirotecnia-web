<?php

namespace App\Core\Data\Entities;

class Equipment
{
    public $id;

    public $name;

    public $description;

    public $unit;

    public $quantity;

    public function __construct(array $attributes = [])
    {
        // Asigna los atributos dinámicamente
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public static function fromEquipment($equipment)
    {
        return new Equipment([
            'id' => $equipment->id,
            'name' => $equipment->name,
            'description' => $equipment->description,
            'unit' => $equipment->unit,
            'quantity' => $equipment->pivot?->quantity ?? 0,
        ]);
    }
}
