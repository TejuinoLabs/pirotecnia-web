<?php

namespace App\Core\Data\Services;

use App\Core\Data\Entities\Package;
use App\Models\Package as ModelsPackage;
use Illuminate\Support\Collection;

class PackageService
{
    public function paginate(int $page, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        return ModelsPackage::with(['products', 'equipments'])->orderBy('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->through(fn ($package) => Package::fromPackage($package));
    }

    public function all(): Collection
    {
        try {
            $eloquentPackages = ModelsPackage::with(['products', 'equipments'])->get();
            $packages = $eloquentPackages->map(function ($eloquentPackage) {
                return Package::fromPackage($eloquentPackage);
            });

            return $packages;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function find(int $packageId): ?Package
    {
        try {
            $eloquentPackage = ModelsPackage::find($packageId);
            $package = Package::fromPackage($eloquentPackage);

            return $package;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findByEventId(int $eventId): Collection
    {
        try {
            $eloquentPackages = ModelsPackage::where('event_id', $eventId)->get();
            $packages = $eloquentPackages->map(function ($eloquentPackage) {
                return Package::fromPackage($eloquentPackage);
            });

            return $packages;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function create(Package $package): ?Package
    {
        try {
            $eloquentPackage = new ModelsPackage;
            $eloquentPackage->fill([
                'name' => $package->name,
                'description' => $package->description,
                'price' => $package->price,
                'duration' => $package->duration,
                'video_url' => $package->video_url,
                'experience_level_id' => $package->experience_level_id,
            ]);
            $eloquentPackage->save();
            $package->id = $eloquentPackage->id;

            return $package;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update(Package $package): ?Package
    {
        try {
            $eloquentPackage = ModelsPackage::find($package->id);
            $eloquentPackage->fill([
                'name' => $package->name,
                'description' => $package->description,
                'price' => $package->price,
                'duration' => $package->duration,
                'video_url' => $package->video_url,
                'experience_level_id' => $package->experience_level_id,
            ]);
            $eloquentPackage->save();

            return $package;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(int $packageId): bool
    {
        try {
            $eloquentPackage = ModelsPackage::find($packageId);
            $eloquentPackage->delete();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function searchPackages(string $searchTerm): Collection
    {
        try {
            // TODO: Implement searchPackages() method
            return new Collection;
        } catch (\Exception $e) {
            return new Collection;
        }
    }
}
