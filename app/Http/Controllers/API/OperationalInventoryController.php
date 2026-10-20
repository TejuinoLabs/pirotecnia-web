<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Package;
use App\Models\Product;
use App\Services\Inventory\AuditService;
use App\Services\Inventory\EventCoverageService;
use App\Services\Inventory\EventDispatchService;
use App\Services\Inventory\EventPickingService;
use App\Services\Inventory\EventPreparationService;
use App\Services\Inventory\EventRequirementService;
use App\Services\Inventory\EventReturnService;
use App\Services\Inventory\IdempotencyService;
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\PackageRequirementService;
use App\Services\Inventory\ProductCatalogService;
use App\Services\Inventory\ProductMatcherService;
use App\Services\Inventory\PurchaseReceiptService;
use App\Services\Inventory\RequirementAllocationService;
use App\Services\Inventory\TechnicianReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationalInventoryController extends Controller
{
    public function products(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'category_id' => ['nullable', 'integer']]);

        return Product::query()->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))->orderBy('id')->paginate(15)->withQueryString()->through(function ($product) {
            $data = $product->toArray();
            $data['image_url'] = app(\App\Services\Admin\AdminMediaService::class)->present($product->image_url);

            return $data;
        });
    }

    public function dashboard()
    {
        return app(\App\Services\Inventory\InventoryDashboardAggregates::class)->summary();
    }

    public function audits()
    {
        return DB::table('inventory_audits')->orderByDesc('id')->paginate(15)->withQueryString();
    }

    public function saveProduct(Request $request, ?int $product = null)
    {
        return response()->json(app(ProductCatalogService::class)->save($request->all(), $request->user()->id, $product), $product ? 200 : 201);
    }

    public function product(int $product)
    {
        return app(ProductCatalogService::class)->show($product);
    }

    public function inventory(Request $request)
    {
        $data = $request->validate(['inventory_area' => ['nullable', 'in:polvorin,stand,raw_material'], 'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'], 'category_id' => ['nullable', 'integer', 'exists:categories,id']]);
        $products = Product::when(isset($data['category_id']), fn ($q) => $q->where('category_id', $data['category_id']))->paginate(15)->withQueryString();
        $area = $data['inventory_area'] ?? null;
        if ($area && isset($data['location_id'])) {
            $location = InventoryData::get('inventory_locations', $data['location_id']);
            InventoryData::assert(app(\App\Services\Inventory\InventoryLocationService::class)->area($location) === $area, 'La ubicación no pertenece al área seleccionada.');
        }
        $demand = app(\App\Services\Inventory\InventoryDemandService::class)->project($data['location_id'] ?? null);
        if ($area === 'stand') {
            $demand = [];
        } elseif ($area && ! isset($data['location_id'])) {
            $demand = [];
            foreach (DB::table('inventory_locations')->where('inventory_area', $area)->where('active', true)->where('type', 'warehouse')->pluck('id') as $location) {
                foreach (app(\App\Services\Inventory\InventoryDemandService::class)->project($location) as $product => $quantity) {
                    $demand[$product] = ($demand[$product] ?? 0) + $quantity;
                }
            }
        }
        $catalog = $products->getCollection();
        app(ProductMatcherService::class)->loadAttributes($catalog);
        $context = app(InventoryAvailabilityService::class)->context($data['location_id'] ?? null, null, $area, $catalog->pluck('id'));
        $products->setCollection($catalog->map(fn ($p) => $p->attributesToArray() + app(InventoryAvailabilityService::class)->stock($p, $data['location_id'] ?? null, null, null, $demand, $area, $context)));

        return $products;
    }

    public function movements(Request $request)
    {
        $data = $request->validate(['product_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'location_id' => ['nullable', 'integer'], 'movement_type' => ['nullable', Rule::in(InventoryMovementService::TYPES)], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'event_id' => ['nullable', 'integer'], 'purchase_id' => ['nullable', 'integer'], 'created_by' => ['nullable', 'integer']]);
        $query = DB::table('inventory_movements')->join('products', 'products.id', '=', 'product_id')->leftJoin('inventory_locations as origin', 'origin.id', '=', 'from_location_id')->leftJoin('inventory_locations as destination', 'destination.id', '=', 'to_location_id')->join('users', 'users.id', '=', 'created_by');
        foreach (['product_id', 'movement_type', 'created_by'] as $field) {
            if (isset($data[$field])) {
                $query->where('inventory_movements.'.$field, $data[$field]);
            }
        }
        if (isset($data['category_id'])) {
            $query->where('products.category_id', $data['category_id']);
        }
        if (isset($data['location_id'])) {
            $query->where(fn ($q) => $q->where('from_location_id', $data['location_id'])->orWhere('to_location_id', $data['location_id']));
        }
        if (isset($data['date_from'])) {
            $query->where('occurred_at', '>=', $data['date_from'].' 00:00:00');
        }
        if (isset($data['date_to'])) {
            $query->where('occurred_at', '<', \Carbon\CarbonImmutable::parse($data['date_to'])->addDay()->startOfDay()->toDateTimeString());
        }
        if (isset($data['event_id'])) {
            $query->where(fn ($q) => $q->where(fn ($q) => $q->where('source_type', 'event_dispatch')->whereIn('source_id', DB::table('event_dispatches')->where('event_id', $data['event_id'])->select('id')))->orWhere(fn ($q) => $q->where('source_type', 'event_return')->whereIn('source_id', DB::table('event_returns')->where('event_id', $data['event_id'])->select('id'))));
        }
        if (isset($data['purchase_id'])) {
            $query->where('source_type', 'purchase_receipt')->whereIn('source_id', DB::table('purchase_receipts')->where('purchase_id', $data['purchase_id'])->select('id'));
        }
        $query->select('inventory_movements.*', 'products.name as product_name', 'products.caliber as product_caliber', 'origin.name as origin', 'destination.name as destination', 'users.name as actor');
        // Running balance reconstructed from the entire ledger, not just filtered rows.
        $location = isset($data['location_id']) ? (int) $data['location_id'] : null;
        $case = $location ? '(CASE WHEN ledger.to_location_id = ? THEN ledger.quantity ELSE 0 END - CASE WHEN ledger.from_location_id = ? THEN ledger.quantity ELSE 0 END)' : '(CASE WHEN ledger.to_location_id IS NOT NULL THEN ledger.quantity ELSE 0 END - CASE WHEN ledger.from_location_id IS NOT NULL THEN ledger.quantity ELSE 0 END)';
        $query->selectRaw('(SELECT COALESCE(SUM('.$case.'), 0) FROM inventory_movements ledger WHERE ledger.product_id = inventory_movements.product_id AND ledger.id <= inventory_movements.id) as balance', $location ? [$location, $location] : []);

        return $query->orderByDesc('inventory_movements.id')->paginate(15)->withQueryString()->through(function ($movement) {
            $movement->product_name = (new Product(['name' => $movement->product_name, 'caliber' => $movement->product_caliber]))->display_name;
            unset($movement->product_caliber);

            return $movement;
        });
    }

    public function balance(Request $request, int $product)
    {
        $data = $request->validate(['location_id' => ['required', 'integer', 'exists:inventory_locations,id']]);
        $row = Product::findOrFail($product);

        return ['product_id' => $row->id, 'location_id' => (int) $data['location_id'], 'quantity' => (float) (DB::table('inventory_balances')->where('product_id', $product)->where('location_id', $data['location_id'])->value('quantity') ?? 0)];
    }

    public function count(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id'], 'location_id' => ['required', 'integer', 'exists:inventory_locations,id'], 'quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'], 'expected_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'], 'purpose' => ['required', 'in:initial,correction'], 'notes' => ['required', 'string', 'max:2000']]);

        return $this->idempotent($request, 'inventory:count', $data, fn () => app(InventoryMovementService::class)->count($data, $request->user()->id));
    }

    public function adjustment(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id'], 'location_id' => ['required', 'integer', 'exists:inventory_locations,id'], 'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'], 'direction' => ['required', 'in:in,out'], 'notes' => ['required', 'string', 'max:2000']]);
        $id = DB::transaction(function () use ($data, $request) {
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            if ($data['direction'] === 'out') {
                $stock = app(InventoryAvailabilityService::class)->stock($product, $data['location_id']);
                InventoryData::assert((float) $data['quantity'] <= $stock['available_stock'], 'El ajuste excede las existencias disponibles.', 'INVENTORY_STOCK_INSUFFICIENT');
            }

            return app(InventoryMovementService::class)->record($data['product_id'], (float) $data['quantity'], $data['direction'] === 'out' ? $data['location_id'] : null, $data['direction'] === 'in' ? $data['location_id'] : null, 'adjustment_'.$data['direction'], $request->user()->id, $data['notes']);
        });

        return response()->json(['movement_id' => $id], 201);
    }

    public function correction(Request $request, int $movement)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return ['movement_id' => app(InventoryMovementService::class)->reverse($movement, $request->user()->id, $data['reason'])];
    }

    public function adopt(Request $request, int $location)
    {
        return app(InventoryMovementService::class)->adopt($location, $request->user()->id);
    }

    public function purchases(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', 'in:draft,ordered,partial,received,cancelled'], 'q' => ['nullable', 'string', 'max:255']]);

        return DB::table('purchases')->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? null, fn ($query, $search) => $query->where('supplier_name', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate(15)->withQueryString();
    }

    public function purchase(int $purchase)
    {
        return app(PurchaseReceiptService::class)->show($purchase);
    }

    public function previewPurchaseDocument(Request $request)
    {
        $request->validate(['document' => ['required', 'file', 'mimes:pdf', 'max:5120']]);

        return app(\App\Services\Purchases\PurchaseDocumentImportService::class)->preview($request->file('document'), $request->user()->id);
    }

    public function createPurchaseDocumentProduct(Request $request)
    {
        $request->validate(['token' => ['required', 'uuid']]);

        return response()->json(app(\App\Services\Purchases\PurchaseDocumentImportService::class)->createProduct($request->input('token'), $request->all(), $request->user()->id), 201);
    }

    public function importPurchaseDocument(Request $request)
    {
        $request->validate(['token' => ['required', 'uuid']]);

        return response()->json(app(\App\Services\Purchases\PurchaseDocumentImportService::class)->register($request->input('token'), $request->all(), $request->user()), 201);
    }

    public function createPurchase(Request $request)
    {
        $data = app(PurchaseReceiptService::class)->validate($request->all());
        if (($data['status'] ?? null) === 'received') {
            app(\App\Services\Inventory\InventoryAccess::class)->authorize($request->user(), 'purchases.receive');
        }
        $result = $this->idempotent($request, 'purchase:create', $data, fn () => app(PurchaseReceiptService::class)->create($data, $request->user()->id));

        return response()->json($result, 201);
    }

    public function updatePurchase(Request $request, int $purchase)
    {
        $data = app(PurchaseReceiptService::class)->validate($request->all(), true);

        return $this->idempotent($request, 'purchase:'.$purchase.':update', $data, fn () => app(PurchaseReceiptService::class)->update($purchase, $data, $request->user()->id));
    }

    public function receivePurchase(Request $request, int $purchase)
    {
        $data = $request->validate(['warehouse_location_id' => ['required', 'integer', 'exists:inventory_locations,id'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'], 'items.*' => ['array:purchase_item_id,quantity'], 'items.*.purchase_item_id' => ['required', 'integer', 'distinct', 'exists:operational_purchase_items,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3']]);

        return $this->idempotent($request, 'purchase:'.$purchase.':receive', $data, fn () => app(PurchaseReceiptService::class)->receive($purchase, $data, $request->user()->id));
    }

    public function cancelPurchase(Request $request, int $purchase)
    {
        return app(PurchaseReceiptService::class)->cancel($purchase, $request->user()->id);
    }

    public function requirement(Request $request, int $package)
    {
        return response()->json(app(PackageRequirementService::class)->create($package, $request->all(), $request->user()->id), 201);
    }

    public function package(int $package)
    {
        return app(\App\Services\Admin\AdminPackageService::class)->show($package);
    }

    public function packages()
    {
        return Package::paginate(50);
    }

    public function savePackage(Request $request, ?int $package = null)
    {
        $data = $request->validate(['name' => [$package ? 'sometimes' : 'required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'price' => [$package ? 'sometimes' : 'required', 'numeric', 'min:0'], 'duration' => [$package ? 'sometimes' : 'required', 'string'], 'event_type_id' => ['nullable', 'integer', 'exists:event_types,id'], 'experience_level_id' => ['nullable', 'integer', 'exists:experience_levels,id']]);

        return DB::transaction(function () use ($data, $package, $request) {
            $model = $package ? Package::findOrFail($package) : new Package;
            $model->fill($data + ['description' => ''])->save();
            app(AuditService::class)->record($request->user()->id, 'package_saved', 'package', $model->id, $data);

            return $this->package($model->id);
        });
    }

    public function deletePackage(Request $request, int $package)
    {
        // Historical snapshots must outlive catalog changes. Archive used packages.
        Package::findOrFail($package)->update(['status' => 'inactive']);
        app(AuditService::class)->record($request->user()->id, 'package_archived', 'package', $package);

        return ['archived' => true];
    }

    public function createEvent(Request $request)
    {
        $data = $request->validate(['event_type_id' => ['nullable', 'integer', 'exists:event_types,id'], 'date' => ['required', 'date'], 'event_date' => ['required', 'date'], 'phone' => ['required', 'string', 'max:255'], 'client_name' => ['required', 'string', 'max:255'], 'client_address' => ['nullable', 'string'], 'event_address' => ['required', 'string'], 'notes' => ['nullable', 'string'], 'package_id' => ['required', 'integer', 'exists:packages,id'], 'package_quantity' => ['integer', 'min:1']]);
        $data['date'] = \Carbon\CarbonImmutable::parse($data['date'], config('mobile.event_timezone'))->toDateString();
        $data['event_date'] = \Carbon\CarbonImmutable::parse($data['event_date'], config('mobile.event_timezone'))->setTimezone(config('mobile.event_timezone'))->toDateTimeString();

        return DB::transaction(function () use ($data, $request) {
            $quantity = $data['package_quantity'] ?? 1;
            unset($data['package_quantity']);
            $event = Event::create($data);
            $package = Package::findOrFail($data['package_id']);
            abort_unless($package->status !== 'inactive', 422, 'El paquete está archivado.');
            $event->packages()->attach($package->id, ['quantity' => $quantity, 'price' => $package->price]);
            foreach ($package->materials as $product) {
                $event->products()->attach($product->id, ['quantity' => $product->pivot->quantity * $quantity, 'price' => $product->pivot->price ?? 0]);
            }
            foreach ($package->equipments as $equipment) {
                $event->equipments()->attach($equipment->id, ['quantity' => $equipment->pivot->quantity * $quantity]);
            }
            app(EventRequirementService::class)->snapshot($event, $request->user()->id);

            return response()->json(['event' => $event->fresh()->toArray(), 'coverage' => app(EventCoverageService::class)->coverage($event->id)], 201);
        });
    }

    public function snapshot(Request $request, int $event)
    {
        return ['requirements' => app(EventRequirementService::class)->snapshot(Event::findOrFail($event), $request->user()->id)];
    }

    public function coverage(Request $request, int $event)
    {
        $data = $request->validate(['location_id' => ['nullable', 'integer', 'exists:inventory_locations,id']]);

        return app(EventCoverageService::class)->coverage($event, $data['location_id'] ?? null);
    }

    public function candidates(Request $request, int $event, int $requirement)
    {
        $data = $request->validate(['location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'], 'include_protected' => ['nullable', Rule::in(['true', 'false', '1', '0'])]]);
        $row = InventoryData::get('event_requirements', $requirement);
        abort_unless($row->event_id === $event, 404);

        return ['candidates' => app(ProductMatcherService::class)->candidates(InventoryData::get('events', $event), $row, $data['location_id'] ?? null, $request->boolean('include_protected', true))];
    }

    public function allocate(Request $request, int $event, int $requirement)
    {
        $data = $request->validate(['warehouse_location_id' => ['required', 'integer', 'exists:inventory_locations,id'], 'allocations' => ['required', 'array', 'min:1'], 'allocations.*' => ['array:product_id,quantity'], 'allocations.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'], 'allocations.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'], 'notes' => ['nullable', 'string', 'max:2000'], 'override_reason' => ['nullable', 'string', 'max:2000']]);

        return app(RequirementAllocationService::class)->allocate($event, $requirement, $data, $request->user());
    }

    public function replace(Request $request, int $allocation)
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id'], 'reason' => ['required', 'string', 'max:2000'], 'override_reason' => ['nullable', 'string', 'max:2000']]);

        return app(RequirementAllocationService::class)->replace($allocation, $data, $request->user());
    }

    public function preparations()
    {
        return DB::table('events')->whereNotIn('logistics_status', ['closed'])->orderBy('event_date')->paginate(15)->withQueryString();
    }

    public function preparation(int $event)
    {
        return app(EventPreparationService::class)->show($event);
    }

    public function startPreparation(Request $request, int $event)
    {
        return app(EventPreparationService::class)->transition($event, false, $request->user()->id);
    }

    public function completePreparation(Request $request, int $event)
    {
        return app(EventPreparationService::class)->transition($event, true, $request->user()->id);
    }

    public function createPicking(Request $request, int $event)
    {
        $data = $request->validate(['warehouse_location_id' => ['required', 'integer', 'exists:inventory_locations,id']]);

        return app(EventPickingService::class)->create($event, $data['warehouse_location_id'], $request->user()->id);
    }

    public function picking(int $event, int $picking)
    {
        return app(EventPickingService::class)->show($event, $picking);
    }

    public function confirmPickingItem(Request $request, int $event, int $picking, int $item)
    {
        $data = $request->validate(['quantity_picked' => ['required', 'numeric', 'gt:0', 'decimal:0,3']]);

        return app(EventPickingService::class)->confirm($event, $picking, $item, (float) $data['quantity_picked'], $request->user()->id);
    }

    public function completePicking(Request $request, int $event, int $picking)
    {
        return app(EventPickingService::class)->complete($event, $picking, $request->user()->id);
    }

    public function dispatches(int $event)
    {
        InventoryData::get('events', $event);

        return ['dispatches' => DB::table('event_dispatches')->where('event_id', $event)->get()->toArray()];
    }

    public function createDispatch(Request $request, int $event)
    {
        $data = $request->validate(['picking_id' => ['required', 'integer', 'exists:event_pickings,id'], 'technician_id' => ['required', 'integer', 'exists:users,id'], 'notes' => ['nullable', 'string', 'max:2000']]);

        return app(EventDispatchService::class)->create($event, $data, $request->user()->id);
    }

    public function dispatch(int $dispatch)
    {
        return app(EventDispatchService::class)->show($dispatch);
    }

    public function confirmDispatch(Request $request, int $dispatch)
    {
        return $this->idempotent($request, 'dispatch:'.$dispatch.':confirm', [], fn () => app(EventDispatchService::class)->confirm($dispatch, $request->user()->id));
    }

    public function identifyDispatch(Request $request, int $dispatch)
    {
        $data = $request->validate(['expected_version' => ['present', 'nullable', 'integer', 'min:1'], 'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['array:dispatch_item_id,serial_numbers'], 'items.*.dispatch_item_id' => ['required', 'integer', 'distinct'],
            'items.*.serial_numbers' => ['required', 'array', 'min:1', 'max:500'], 'items.*.serial_numbers.*' => ['required', 'string', 'max:255', 'distinct']]);

        return $this->idempotent($request, 'dispatch:'.$dispatch.':identify', $data, fn () => app(EventDispatchService::class)->identify($dispatch, $data, $request->user()->id));
    }

    public function technicianDispatch(Request $request, int $event)
    {
        return app(\App\Services\Mobile\MobileDispatchService::class)->forEvent($event, $request->user());
    }

    public function receiveTechnician(Request $request, int $dispatch)
    {
        $owned = app(\App\Services\Mobile\MobileDispatchService::class)->owned($dispatch, $request->user());
        app(\App\Services\Inventory\InventoryAccess::class)->authorize($request->user(), 'mobile.receive');
        if ($owned->version !== null) {
            \Illuminate\Support\Facades\Validator::make(['key' => $request->header('Idempotency-Key')], ['key' => ['required', 'string', 'max:100']])->validate();
            $request->validate(['items.*.received_quantity' => \App\Services\Mobile\MobileContract::quantityRule()]);
        }
        $data = $request->validate(['expected_version' => ['sometimes', 'integer', 'min:1'], 'items' => ['required', 'array', 'min:1', 'max:500'], 'items.*.dispatch_item_id' => ['required', 'integer', 'distinct'], 'items.*' => ['array:dispatch_item_id,received_quantity,status,notes,serial_number'], 'items.*.serial_number' => ['nullable', 'string', 'max:255'], 'items.*.received_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'], 'items.*.status' => ['required', 'in:received,missing,damaged,quantity_mismatch'], 'items.*.notes' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'], 'device_info' => ['nullable', 'string', 'max:2000']]);
        try {
            return $this->idempotent($request, 'technician:'.$dispatch.':receive', $data, fn () => app(TechnicianReceiptService::class)->receive($dispatch, $data, $request->user()->id));
        } catch (\App\Services\Inventory\InventoryException $e) {
            if ($e->errorCode === 'IDEMPOTENCY_KEY_CONFLICT') {
                throw new \App\Services\Inventory\InventoryException($e->getMessage(), $e->errorCode, $e->errors, 409);
            }
            throw $e;
        }
    }

    public function createReturn(Request $request, int $event)
    {
        $rules = ['event_dispatch_id' => ['required', 'integer', 'exists:event_dispatches,id'], 'warehouse_location_id' => ['required', 'integer', 'exists:inventory_locations,id'], 'damaged_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'], 'returned_by' => ['nullable', 'integer', 'exists:users,id'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'], 'items.*.dispatch_item_id' => ['required', 'integer', 'distinct'], 'items.*' => ['array:dispatch_item_id,quantity_consumed,quantity_returned,quantity_damaged,quantity_lost,notes,serial_number'], 'items.*.serial_number' => ['nullable', 'string', 'max:255'], 'items.*.notes' => ['nullable', 'string', 'max:2000']];
        foreach (['consumed', 'returned', 'damaged', 'lost'] as $field) {
            $rules['items.*.quantity_'.$field] = ['required', 'numeric', 'min:0', 'decimal:0,3'];
        }
        $data = $request->validate($rules);

        return $this->idempotent($request, 'event:'.$event.':return', $data, fn () => app(EventReturnService::class)->create($event, $data, $request->user()->id));
    }

    public function confirmReturn(Request $request, int $return)
    {
        return $this->idempotent($request, 'return:'.$return.':confirm', [], fn () => app(EventReturnService::class)->confirm($return, $request->user()->id));
    }

    private function idempotent(Request $request, string $operation, array $payload, callable $callback): array
    {
        return app(IdempotencyService::class)->run($request->user()->id, $request->header('Idempotency-Key'), $operation, $payload, $callback);
    }
}
