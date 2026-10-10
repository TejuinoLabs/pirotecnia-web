<?php

namespace App\Livewire\Panel\Settings\Products\Show;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsTable extends Component
{
    use WithPagination;

    public $productId;

    public $product;

    public $materials;

    public $materialId;

    public $isAddProduct = false;

    public $isEditMode = false;

    public function mount($product)
    {
        $this->product = $product;

        $this->materials = Product::where('product_role_id', 1)->where('id', '!=', $product->id)->get();
    }

    public function render()
    {
        return view('livewire.panel.settings.products.show.products-table', ['components' => $this->product->products()->orderBy('products.name')->paginate(15, ['products.*'], 'components_page')]);
    }

    // switch to add product mode
    public function switchToAddProductMode()
    {
        $this->isAddProduct = ! $this->isAddProduct;
    }

    // switch to add new product mode
    public function switchToAddNewMaterialMode($value = true) {}

    // switch to edit mode
    public function switchToEditMode($materialId) {}

    // add product to package
    public function addMaterialToProduct()
    {
        if ($this->product->products->contains($this->materialId)) {
            return;
        }
        // Verify if products is empty
        if ($this->product->products->isEmpty()) {
            $this->product->product_role_id = 2;
            $this->product->save();
        }
        $this->product->products()->attach($this->materialId, ['quantity' => 1, 'price' => 0]);
        $this->product->refresh();
        $this->clearNewMaterialForm();
    }

    // remove product from package
    public function removeMaterialFromPackage($materialId)
    {
        $this->product->products()->detach($materialId);

        if ($this->product->products->isEmpty()) {
            $this->product->product_role_id = 1;
            $this->product->save();
        }
        $this->product->refresh();
    }

    // edit product in package
    public function editMaterialInPackage($materialId)
    {

        $this->clearNewMaterialForm();
    }

    // add new product to package
    public function addNewMaterialToPackage()
    {

        $this->clearNewMaterialForm();
    }

    // cancel add product
    public function cancelAddMaterial()
    {

        $this->clearNewMaterialForm();
    }

    // cancel edit product
    public function cancelEditMaterial()
    {

        $this->clearNewMaterialForm();
    }

    // clear new product form
    public function clearNewMaterialForm()
    {
        $this->materialId = null;
        $this->isAddProduct = false;
        $this->isEditMode = false;
    }
}
