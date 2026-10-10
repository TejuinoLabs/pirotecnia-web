<?php

namespace App\Imports;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        //
        $products = collect();
        if (count($collection) > 0) {
            $productsExcel = $collection;
            foreach ($productsExcel as $index => $productExcel) {
                // First row is the header
                if ($index > 0) {
                    // add product to collection
                    $product = collect([
                        'name' => $productExcel[0],
                        'shape' => $productExcel[1],
                        'Duration' => $productExcel[2],
                        'Shots' => $productExcel[3],
                        'Caliber' => $productExcel[4],
                        'Price' => $productExcel[5],
                        'Stock' => $productExcel[6] ?? 0,

                    ]);
                    if ($product['name'] != null) {

                        DB::transaction(function () use ($product) {
                            $model = $this->addProductToDB($product);
                            $this->addProductsToInventory($product, $model);
                        });
                        $products->push($product);
                    }
                }
            }
        }

        return $products;
    }

    private function addProductToDB($product)
    {
        $productDB = new Product;
        $productDB->product_role_id = 1;
        $productDB->name = $product['name'] ?? '';
        $productDB->description = $product['name'];
        $productDB->unit = 'pza';
        $productDB->duration = $product['Duration'];
        $productDB->shots = $product['Shots'];
        $productDB->caliber = $product['Caliber'];
        $productDB->shape = $product['shape'];
        $productDB->save();

        return $productDB;
    }

    private function addProductsToInventory($product, Product $productDB)
    {
        $inventory = Inventory::find(1);

        $location = DB::table('inventory_locations')->where('legacy_inventory_id', 1)->whereNotNull('adopted_at')->first();
        if ($location) {
            abort_unless(auth()->user(), 403);
            app(\App\Services\Inventory\InventoryAccess::class)->authorize(auth()->user(), 'inventory.adjust');
            $productDB->forceFill(['purchase_cost' => $product['Price']])->save();
            \App\Services\Inventory\InventoryData::assert((float) $product['Stock'] >= 0, 'Stock de importación negativo.');
            if ((float) $product['Stock'] > 0) app(\App\Services\Inventory\InventoryMovementService::class)->record($productDB->id, (float) $product['Stock'], null, $location->id, 'adjustment_in', auth()->id(), 'Saldo de apertura de artículo importado desde Excel');

            return;
        }

        $inventory->products()->attach($productDB->id, [
            'quantity' => $product['Stock'],
            'price' => $product['Price'],
        ]);

    }
}
