<?php

namespace App\Core\UseCases\Purchases;

use App\Core\Data\Repositories\EquipmentRepositoryInterface;
use App\Core\Data\Repositories\ProductRepositoryInterface;

class GetPurchaseCatalog
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private EquipmentRepositoryInterface $equipmentRepository,
    ) {}

    public function execute(): array
    {
        $products = $this->productRepository->all();

        return [
            'products' => $products
                ->filter(fn ($product) => (int) $product->product_role_id !== 2)
                ->values(),
            'materials' => $products
                ->filter(fn ($product) => (int) $product->product_role_id === 2)
                ->values(),
            'equipments' => $this->equipmentRepository->all()->values(),
        ];
    }
}
