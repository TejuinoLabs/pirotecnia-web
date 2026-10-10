<?php

namespace App\Services\Inventory;

class InventoryLocationService
{
    public const AREAS = ['polvorin', 'stand', 'raw_material'];

    public function area(object $location): string
    {
        return $location->type === 'stand' ? 'stand' : ($location->type === 'warehouse' ? $location->inventory_area : 'external');
    }

    public function assertStorage(object $location): void
    {
        InventoryData::assert($location->active && in_array($location->type, ['warehouse', 'stand'], true) && in_array($this->area($location), self::AREAS, true), 'Selecciona un polvorín, puesto o almacén de materia prima activo.');
    }
}
