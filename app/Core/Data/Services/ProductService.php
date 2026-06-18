<?php

namespace App\Core\Data\Services;

use App\Core\Data\Entities\Product;
use App\Models\Product as ModelsProduct;
use Illuminate\Support\Collection;

class ProductService
{
    public function all(): Collection
    {
        try {
            $eloquentProducts = ModelsProduct::with('inventories')->orderBy('name')->get();
            $products = $eloquentProducts->map(function ($eloquentProduct) {
                return Product::fromProduct($eloquentProduct);
            });

            return $products;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function find(int $productId): ?Product
    {
        try {
            $eloquentProduct = ModelsProduct::find($productId);
            $product = Product::fromProduct($eloquentProduct);

            return $product;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findByInventoryId(int $inventoryId): Collection
    {
        try {
            $eloquentProducts = ModelsProduct::where('inventory_id', $inventoryId)->get();
            $products = $eloquentProducts->map(function ($eloquentProduct) {
                return Product::fromProduct($eloquentProduct);
            });

            return $products;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function findByPackageId(int $packageId): Collection
    {
        try {
            $eloquentProducts = ModelsProduct::where('package_id', $packageId)->get();
            $products = $eloquentProducts->map(function ($eloquentProduct) {
                return Product::fromProduct($eloquentProduct);
            });

            return $products;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function getByPackageIds(Collection $packageIds): Collection
    {
        try {
            $eloquentProducts = ModelsProduct::whereIn('package_id', $packageIds)->get();
            $products = $eloquentProducts->map(function ($eloquentProduct) {
                return Product::fromProduct($eloquentProduct);
            });

            return $products;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function getByEventId(int $eventId): Collection
    {
        try {
            $eloquentProducts = ModelsProduct::where('event_id', $eventId)->get();
            $products = $eloquentProducts->map(function ($eloquentProduct) {
                return Product::fromProduct($eloquentProduct);
            });

            return $products;
        } catch (\Exception $e) {
            return new Collection;
        }
    }

    public function create(Product $product): ?Product
    {
        try {
            $eloquentProduct = new ModelsProduct;
            $eloquentProduct->fill([
                'product_role_id' => $product->product_role_id,
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit,
                'duration' => $product->duration,
                'shots' => $product->shots,
                'caliber' => $product->caliber,
                'shape' => $product->shape,
            ]);
            $eloquentProduct->save();
            $product->id = $eloquentProduct->id;

            return $product;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update(Product $product): ?Product
    {
        try {
            $eloquentProduct = ModelsProduct::find($product->id);
            $eloquentProduct->fill([
                'product_role_id' => $product->product_role_id,
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit,
                'duration' => $product->duration,
                'shots' => $product->shots,
                'caliber' => $product->caliber,
                'shape' => $product->shape,
            ]);
            $eloquentProduct->save();

            return $product;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(int $productId): bool
    {
        try {
            $eloquentProduct = ModelsProduct::find($productId);
            $eloquentProduct->delete();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function searchProducts(string $searchTerm): Collection
    {
        try {
            $eloquentProducts = ModelsProduct::where('name', 'like', '%'.$searchTerm.'%')->get();
            $products = $eloquentProducts->map(function ($eloquentProduct) {
                return new Product(
                    $eloquentProduct->id,
                    $eloquentProduct->name,
                    $eloquentProduct->description,
                    $eloquentProduct->price,
                    $eloquentProduct->inventory_id
                );
            });

            return $products;
        } catch (\Exception $e) {
            return new Collection;
        }
    }
}
