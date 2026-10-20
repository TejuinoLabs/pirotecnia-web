<?php

namespace App\Core\UseCases\Packages;

use App\Core\Data\Repositories\PackageRepositoryInterface;
use Illuminate\Support\Collection;

class GetAllPackages
{
    public function __construct(
        private PackageRepositoryInterface $packageRepository
    ) {}

    public function execute(int $page): Collection
    {
        $perPage = 10;
        $paginator = $this->packageRepository->paginate($page, $perPage);

        return new Collection([
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $perPage,
            'packages' => collect($paginator->items()),
        ]);
    }
}
