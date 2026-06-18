<?php

namespace App\Http\Controllers\Panel;

use App\Core\UseCases\Purchases\GetPurchaseCatalog;
use App\Core\UseCases\Purchases\StorePurchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StorePurchaseRequest;
use App\Models\Purchase;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private GetPurchaseCatalog $getPurchaseCatalog,
        private StorePurchase $storePurchase,
    ) {}

    public function index()
    {
        $purchases = Purchase::with(['products', 'equipments'])
            ->latest('date')
            ->latest('id')
            ->get();
        $itemActive = 6;

        return view('panel.purchases.index', compact('purchases', 'itemActive'));
    }

    public function create()
    {
        $catalog = $this->getPurchaseCatalog->execute();
        $products = $catalog['products'];
        $materials = $catalog['materials'];
        $equipments = $catalog['equipments'];

        return view('panel.purchases.create', compact('products', 'materials', 'equipments'));
    }

    public function store(StorePurchaseRequest $request)
    {
        $purchase = $this->storePurchase->execute(
            $request->toEntity($request->user()->id)
        );
        if (! $purchase) {
            return back()
                ->withInput()
                ->withErrors(['purchase' => 'No se pudo guardar la compra. Revisa los conceptos e intenta de nuevo.']);
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Compra registrada correctamente.');
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
        return view('panel.purchases.edit');
    }

    public function update(Request $request, $id)
    {
        // update purchase and inventory
        // update purchase
        $purchase = Purchase::find($id);
        $purchase->update([
            'date' => $request->date,
        ]);
        // update inventory
        $purchase->products()->updateExistingPivot($request->product_id, [
            'quantity' => $request->quantity,
            'price' => $request->price,
        ]);
    }

    public function destroy($id)
    {
        //

    }
}
