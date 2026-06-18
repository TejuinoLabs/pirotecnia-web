<?php

namespace App\Core\UseCases\Purchases;

use App\Core\Data\Entities\Purchase;
use App\Core\Data\Repositories\PurchaseRepositoryInterface;

class StorePurchase
{
    public function __construct(private PurchaseRepositoryInterface $purchaseRepository) {}

    public function execute(Purchase $purchase): ?Purchase
    {
        return $this->purchaseRepository->create($purchase);
    }
}
