<?php

namespace App\Core\Domains\Repositories;

use App\Core\Data\Entities\Purchase;
use App\Core\Data\Repositories\PurchaseRepositoryInterface;
use App\Core\Data\Services\PurchaseService;
use Illuminate\Support\Collection;

class EloquentPurchaseRepository implements PurchaseRepositoryInterface
{
    public function __construct(private PurchaseService $purchaseService) {}

    public function all(): Collection
    {
        return $this->purchaseService->all();
    }

    public function find(int $purchaseId): ?Purchase
    {
        return $this->purchaseService->find($purchaseId);
    }

    public function create(Purchase $purchase): ?Purchase
    {

        return $this->purchaseService->create($purchase);
    }

    public function update(int $purchaseId, Purchase $purchase): ?Purchase
    {
        $purchase->id = $purchaseId;

        return $this->purchaseService->update($purchase);
    }

    public function delete(int $purchaseId): bool
    {
        return $this->purchaseService->delete($purchaseId);
    }

    public function searchPurchases($searchTerm): Collection
    {
        return $this->purchaseService->searchPurchases($searchTerm);
    }
}
