<?php

namespace App\Core\Domains\Repositories;

use App\Core\Data\Entities\Package;
use App\Core\Data\Repositories\PackageRepositoryInterface;
use App\Core\Data\Services\PackageService;
use Illuminate\Support\Collection;

class EloquentPackageRepository implements PackageRepositoryInterface
{
    public function __construct(private PackageService $packageService) {}

    public function paginate(int $page, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->packageService->paginate($page, $perPage);
    }

    public function all(): Collection
    {
        return $this->packageService->all();
    }

    public function find(int $packageId): ?Package
    {
        return $this->packageService->find($packageId);
    }

    public function findByEventId(int $eventId): Collection
    {
        return $this->packageService->findByEventId($eventId);
    }

    public function create(Package $package): ?Package
    {
        return $this->packageService->create($package);
    }

    public function update(int $packageId, Package $package): ?Package
    {
        return $this->packageService->update($package);
    }

    public function delete(int $packageId): bool
    {
        return $this->packageService->delete($packageId);
    }

    public function searchPackages($searchTerm): Collection
    {
        return $this->packageService->searchPackages($searchTerm);
    }
}
