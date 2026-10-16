<?php

namespace App\Livewire\Panel\Events;

use App\Helper\Reminder;
use App\Models\Employee;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Inventory;
use App\Models\Package;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class EventForm extends Component
{
    public $event;

    public $packages;

    public $employees;

    public $eventTypes;

    public $products = [];

    public $date;

    public $phone;

    public $client_name;

    public $client_address;

    public $event_address;

    public $event_date;

    public $event_time;

    public $event_type_id = 1;

    public $packageMode = 'registered';

    public $package_id = [];

    public $employee_id = [];

    public $customProductSearch = '';

    public $customProductQuantity = 1;

    public $customProducts = [];

    public $newCustomProductName = '';

    public $newCustomProductDescription = '';

    public $newCustomProductUnit = 'pz';

    public $newCustomProductRoleId = 1;

    public $newCustomProductQuantity = 1;

    public $newCustomProductStock = '';

    public $newCustomProductPrice = '';

    public $newCustomProductEventPrice = '';

    public $discountString = '';

    public $discount = 0;

    public $deposit = 0;

    public $viatic = 0;

    public $price = 0;

    public $notes = '';

    public $contract_description = '';

    public $radioSelected = [];

    public $showAlert = false;

    public $enableSave = true;

    public $isEditMode = false;

    public $errorMessage = '';

    public $showPastDateModal = false;

    public $hasConfirmedPastDate = false;

    public $countPackageInputs = 1;

    public $countEmployeeInputs = 1;

    protected function rules(): array
    {
        $rules = [
            'date' => 'required|date_format:Y-m-d',
            'phone' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'client_address' => 'nullable|string|max:255',
            'event_address' => 'required|string|max:255',
            'event_date' => 'required|date_format:Y-m-d',
            'event_time' => 'required|date_format:H:i',
            'event_type_id' => 'required|exists:event_types,id',
            'packageMode' => 'required|in:registered,custom',
        ];

        if ($this->packageMode === 'registered') {
            $rules['package_id'] = ['required', 'array', function ($attribute, $value, $fail) {
                if (empty($this->selectedPackageIds())) {
                    $fail('Selecciona al menos un paquete registrado.');
                }
            }];
            $rules['package_id.*'] = 'nullable|exists:packages,id';
        } else {
            $rules['customProducts'] = ['required', 'array', function ($attribute, $value, $fail) {
                if ($this->selectedCustomProducts()->isEmpty()) {
                    $fail('Agrega al menos un material o producto al paquete personalizado.');
                }
            }];
            $rules['price'] = ['required', function ($attribute, $value, $fail) {
                if ($this->parse_user_amount((string) $value) <= 0) {
                    $fail('El precio final del paquete personalizado es requerido y debe ser mayor a cero.');
                }
            }];
            $rules['contract_description'] = ['nullable', function ($attribute, $value, $fail) {
                if (mb_strlen((string) $this->sanitizeContractDescription()) > 240) {
                    $fail('La descripción para contrato debe ser breve. Usa hasta 240 caracteres.');
                }
            }];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => 'Este campo es requerido.',
            'date_format' => 'Captura una fecha u hora válida.',
            'max' => 'Este campo no debe superar los :max caracteres.',
            'exists' => 'Selecciona una opción válida.',
            'package_id.required' => 'Selecciona al menos un paquete registrado. Este campo es requerido.',
            'customProducts.required' => 'Agrega al menos un material o producto al paquete personalizado.',
        ];
    }

    public function updatedPackageMode(): void
    {
        $this->showAlert = false;
        $this->enableSave = true;
        $this->errorMessage = '';
        $this->resetValidation([
            'package_id',
            'customProducts',
            'price',
            'contract_description',
            'newCustomProductName',
            'newCustomProductUnit',
            'newCustomProductStock',
            'newCustomProductPrice',
            'newCustomProductEventPrice',
        ]);
        $this->refreshPackageProducts();
    }

    public function updatedEventDate(): void
    {
        $this->hasConfirmedPastDate = false;
        $this->showPastDateModal = false;
    }

    public function updatedPackageId($packageId, $key = null): void
    {
        if ((int) $packageId === -1) {
            if ($key !== null) {
                $this->package_id[$key] = '';
            }

            $this->dispatch('openModal', ['id' => 'new-package']);
        }
    }

    public function updatedEmployeeId($employeeId, $key = null): void
    {
        if ((int) $employeeId === -1) {
            if ($key !== null) {
                $this->employee_id[$key] = '';
            }

            $this->dispatch('openModal', ['id' => 'new-employee']);
        }
    }

    public function mount($event = null)
    {
        $this->packages = Package::all();
        $this->eventTypes = EventType::all();
        $this->employees = Employee::all();
        $this->packageMode = $this->packages->isEmpty() ? 'custom' : 'registered';
        $this->isEditMode = $event != null;

        if ($this->isEditMode) {
            $this->event = $event;
            $this->loadEventInputs();

            return;
        }

        $this->date = Carbon::now()->format('Y-m-d');
    }

    #[On('packageCreated')]
    public function savePackageAndUpdate($package): void
    {
        $this->packages = Package::all();
        $this->dispatch('closeModal', ['id' => 'new-package']);
    }

    #[On('employeeCreated')]
    public function saveEmployeeAndUpdate($employee): void
    {
        $this->employees = Employee::all();
        $this->dispatch('closeModal', ['id' => 'new-employee']);
    }

    public function render()
    {
        $this->refreshPackageProducts();

        return view('livewire.panel.events.event-form', [
            'products' => $this->products,
            'customCatalogProducts' => $this->customCatalogProducts(),
            'customSelectionItems' => $this->customSelectionItems(),
            'customSelectedIndex' => $this->selectedCustomProducts()
                ->filter(fn ($row) => (int) ($row['product_id'] ?? 0) > 0)
                ->mapWithKeys(fn ($row) => [$row['product_id'] => $row['quantity']])
                ->all(),
        ]);
    }

    public function addPackageInput(): void
    {
        $this->package_id[] = '';
        $this->countPackageInputs += 1;
    }

    public function removePackageInput($index): void
    {
        if ($this->countPackageInputs <= 1) {
            return;
        }

        unset($this->package_id[$index]);
        $this->package_id = array_values($this->package_id);
        $this->countPackageInputs = max(1, $this->countPackageInputs - 1);
    }

    public function addEmployeeInput(): void
    {
        $this->employee_id[] = '';
        $this->countEmployeeInputs += 1;
    }

    public function removeEmployeeInput($index): void
    {
        if ($this->countEmployeeInputs <= 1) {
            return;
        }

        unset($this->employee_id[$index]);
        $this->employee_id = array_values($this->employee_id);
        $this->countEmployeeInputs = max(1, $this->countEmployeeInputs - 1);
    }

    public function addCustomProduct(int $productId): void
    {
        $product = Product::where('product_role_id', '!=', 3)->find($productId);

        if (! $product) {
            return;
        }

        $quantity = max((int) $this->customProductQuantity, 1);
        $existingIndex = collect($this->customProducts)
            ->search(fn ($item) => (int) ($item['product_id'] ?? 0) === $productId);

        if ($existingIndex !== false) {
            $this->customProducts[$existingIndex]['quantity'] = max(
                (int) ($this->customProducts[$existingIndex]['quantity'] ?? 0) + $quantity,
                1
            );
        } else {
            $this->customProducts[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => '',
            ];
        }

        $this->customProductQuantity = 1;
        $this->showAlert = false;
        $this->enableSave = true;
        $this->errorMessage = '';
        $this->resetValidation(['customProducts']);
    }

    public function removeCustomProduct(int $index): void
    {
        unset($this->customProducts[$index]);
        $this->customProducts = array_values($this->customProducts);
    }

    public function addNewCustomProduct(): void
    {
        $this->resetValidation([
            'newCustomProductName',
            'newCustomProductUnit',
            'newCustomProductStock',
            'newCustomProductPrice',
            'newCustomProductEventPrice',
            'customProducts',
        ]);

        $name = trim((string) $this->newCustomProductName);
        $unit = trim((string) $this->newCustomProductUnit);
        $quantity = max((int) $this->newCustomProductQuantity, 1);
        $inventoryQuantity = max($this->parseNumberInput($this->newCustomProductStock), (float) $quantity);

        $this->newCustomProductName = $name;
        $this->newCustomProductUnit = $unit;
        $this->validate([
            'newCustomProductName' => 'required|string|max:255',
            'newCustomProductUnit' => 'required|string|max:255',
        ]);

        $this->customProducts[] = [
            'product_id' => null,
            'product_role_id' => $this->normalizeProductRoleId($this->newCustomProductRoleId),
            'name' => $name,
            'description' => trim((string) $this->newCustomProductDescription) ?: $name,
            'unit' => $unit,
            'quantity' => $quantity,
            'inventory_quantity' => $inventoryQuantity,
            'inventory_price' => $this->parseNumberInput($this->newCustomProductPrice),
            'price' => $this->parseNumberInput($this->newCustomProductEventPrice),
        ];

        $this->resetNewCustomProductInputs();
        $this->showAlert = false;
        $this->enableSave = true;
        $this->errorMessage = '';
    }

    private function loadEventInputs(): void
    {
        $this->date = $this->event->date;
        $this->phone = $this->event->phone;
        $this->client_name = $this->event->client_name;
        $this->client_address = $this->event->client_address;
        $this->event_address = $this->event->event_address;
        $this->event_date = substr($this->event->event_date, 0, 10);
        $this->event_time = substr($this->event->event_date, 11, 5);
        $this->event_type_id = $this->event->event_type_id;
        $this->package_id = $this->event->packages
            ->flatMap(function ($package) {
                $quantity = max((int) ($package->pivot->quantity ?? 1), 1);

                return array_fill(0, $quantity, $package->id);
            })
            ->values()
            ->toArray();
        $this->employee_id = $this->event->employees->pluck('id')->toArray();
        $this->countPackageInputs = max(count($this->package_id), 1);
        $this->countEmployeeInputs = max($this->event->employees->count(), 1);
        $this->discount = $this->event->discount;
        $this->discountString = $this->event->discount * 100 .'%';
        $this->deposit = $this->event->advance;
        $this->viatic = $this->event->travel_expenses;
        $this->notes = $this->event->notes;
        $this->contract_description = $this->event->contract_description;
        $this->price = $this->event->price;
        $this->packageMode = $this->event->packages->isNotEmpty() ? 'registered' : 'custom';

        if ($this->packageMode === 'custom') {
            $this->customProducts = $this->event->products
                ->map(fn ($product) => [
                    'product_id' => $product->id,
                    'quantity' => (int) ($product->pivot->quantity ?? 1),
                    'price' => (float) ($product->pivot->price ?? 0),
                ])
                ->values()
                ->toArray();
        }
    }

    private function validateStock(): bool
    {
        $plannedProducts = $this->plannedEventProducts();

        if ($plannedProducts->isEmpty()) {
            $this->errorMessage = '';

            return true;
        }

        if ($this->packageMode === 'custom') {
            $existingProducts = Product::with('inventories')
                ->whereIn(
                    'id',
                    $plannedProducts
                        ->pluck('product_id')
                        ->filter(fn ($productId) => (int) $productId > 0)
                        ->map(fn ($productId) => (int) $productId)
                        ->values()
                        ->all()
                )
                ->get()
                ->keyBy('id');

            $lowInventoryNames = $plannedProducts
                ->filter(function ($item) use ($existingProducts) {
                    $productId = (int) ($item['product_id'] ?? 0);

                    if ($productId > 0) {
                        $product = $existingProducts->get($productId);

                        if ($product && $product->inventories->isNotEmpty()) {
                            return (float) ($product->inventories->first()?->pivot?->quantity ?? 0) <= Inventory::MIN_STOCK;
                        }
                    }

                    return (float) ($item['inventory_quantity'] ?? 0) <= Inventory::MIN_STOCK;
                })
                ->map(function ($item) use ($existingProducts) {
                    $productId = (int) ($item['product_id'] ?? 0);

                    if ($productId > 0) {
                        return $existingProducts->get($productId)?->name;
                    }

                    return $item['name'] ?? null;
                })
                ->filter()
                ->values();

            if ($lowInventoryNames->isEmpty()) {
                $this->errorMessage = '';

                return true;
            }

            $visibleNames = $lowInventoryNames->take(4)->implode(', ');
            $remaining = $lowInventoryNames->count() - min($lowInventoryNames->count(), 4);
            $suffix = $remaining > 0 ? ' y '.$remaining.' más' : '';

            $this->errorMessage = 'Inventario bajo detectado en: '.$visibleNames.$suffix.'. Puedes revisar la selección o continuar de todos modos.';

            return false;
        }

        $existingProductIds = $plannedProducts
            ->pluck('product_id')
            ->filter(fn ($productId) => (int) $productId > 0)
            ->map(fn ($productId) => (int) $productId)
            ->values()
            ->all();

        $lowInventoryProducts = Product::with('inventories')
            ->whereIn('id', $existingProductIds)
            ->where(function ($query) {
                $query->whereDoesntHave('inventories')
                    ->orWhereHas('inventories', function ($inventoryQuery) {
                        $inventoryQuery->where('quantity', '<=', Inventory::MIN_STOCK)
                            ->orWhereNull('quantity');
                    });
            })
            ->get();

        $draftLowInventoryProducts = $plannedProducts
            ->filter(fn ($item) => (int) ($item['product_id'] ?? 0) === 0)
            ->filter(fn ($item) => (float) ($item['inventory_quantity'] ?? 0) <= Inventory::MIN_STOCK)
            ->pluck('name');

        if ($lowInventoryProducts->isEmpty() && $draftLowInventoryProducts->isEmpty()) {
            $this->errorMessage = '';

            return true;
        }

        $lowInventoryNames = $lowInventoryProducts
            ->pluck('name')
            ->merge($draftLowInventoryProducts)
            ->filter()
            ->values();

        $visibleNames = $lowInventoryNames->take(4)->implode(', ');
        $remaining = $lowInventoryNames->count() - min($lowInventoryNames->count(), 4);
        $suffix = $remaining > 0 ? ' y '.$remaining.' más' : '';

        $this->errorMessage = 'Inventario bajo detectado en: '.$visibleNames.$suffix.'. Puedes revisar la selección o continuar de todos modos.';

        return false;
    }

    private function parse_user_amount(string $input): float
    {
        $value = trim($input);
        $isPercent = str_contains($value, '%');
        $clean = preg_replace('/[^0-9\-\.,]/', '', $value);

        if ($clean === '' || $clean === '-') {
            return 0.0;
        }

        $clean = str_replace(',', '', $clean);
        $number = (float) $clean;

        return $isPercent ? ($number / 100) : $number;
    }

    private function saveEvent(): Event
    {
        $event = $this->isEditMode ? Event::find($this->event->id) : new Event;
        $addEmployee = false;
        $selectedPackageIds = $this->selectedPackageIds();
        $selectedEmployeeIds = $this->selectedEmployeeIds();
        $existingEmployeeIds = $event->exists ? $event->employees->pluck('id')->toArray() : [];
        $newEmployees = array_diff($selectedEmployeeIds, $existingEmployeeIds);

        if ($this->isEditMode && count($existingEmployeeIds) !== count($selectedEmployeeIds)) {
            $addEmployee = true;
        }

        $event->fill([
            'date' => $this->date,
            'phone' => $this->phone,
            'client_name' => $this->client_name,
            'client_address' => $this->client_address,
            'event_address' => $this->event_address,
            'event_date' => $this->event_date.' '.$this->event_time,
            'event_type_id' => $this->event_type_id,
            'discount' => $this->parse_user_amount((string) $this->discountString),
            'advance' => $this->parse_user_amount((string) $this->deposit),
            'travel_expenses' => $this->formatViatic($this->viatic),
            'notes' => $this->notes,
            'contract_description' => $this->packageMode === 'custom' ? $this->sanitizeContractDescription() : null,
            'price' => $this->parse_user_amount((string) $this->price),
        ]);

        if (! empty($selectedPackageIds)) {
            $event->package()->associate($selectedPackageIds[0]);
        } else {
            $event->package()->dissociate();
        }

        $event->save();

        $this->syncEmployees($event, $selectedEmployeeIds);
        $this->syncPackages($event, $selectedPackageIds);

        if ($addEmployee) {
            $event->employees = $event->employees->whereIn('id', $newEmployees);
        }

        $this->sendReminders($event);

        return $event;
    }

    private function formatViatic($viatic): string
    {
        $amount = $this->parse_user_amount((string) $viatic);

        return number_format($amount, 2, '.', '');
    }

    private function syncEmployees(Event $event, array $selectedEmployeeIds): void
    {
        $event->employees()->sync($selectedEmployeeIds);
    }

    private function syncPackages(Event $event, array $selectedPackageIds): void
    {
        if (empty($selectedPackageIds)) {
            $event->packages()->detach();

            return;
        }

        $packageQuantities = collect($selectedPackageIds)
            ->countBy()
            ->map(fn ($quantity) => max((int) $quantity, 1));

        $packages = Package::whereIn('id', $packageQuantities->keys()->all())
            ->get()
            ->keyBy('id');

        $payload = $packageQuantities
            ->mapWithKeys(function (int $quantity, int $packageId) use ($packages) {
                $package = $packages->get($packageId);

                if (! $package) {
                    return [];
                }

                return [
                    $packageId => [
                        'quantity' => $quantity,
                        'price' => (float) $package->price,
                    ],
                ];
            })
            ->all();

        $event->packages()->sync($payload);
    }

    private function sendReminders(Event $event): void
    {
        if (! $this->isEditMode) {
            Reminder::send($event, 'pushNotification', 4, true);
        }

        if ($event->employees->count() > 0) {
            Reminder::send($event, 'pushNotification', 3);
        }
    }

    private function saveProductsInEvent(Event $event): Event
    {
        $this->ensureCustomProductsInInventory();

        $payload = $this->plannedEventProducts()
            ->mapWithKeys(fn ($item) => [
                $item['product_id'] => [
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? 0,
                    'check_employee' => $this->existingProductFlag($event, $item['product_id'], 'check_employee'),
                    'check_almacen' => $this->existingProductFlag($event, $item['product_id'], 'check_almacen'),
                ],
            ])
            ->all();

        $event->products()->sync($payload);

        return $event;
    }

    public function save()
    {
        $this->validate();
        $this->processDiscount();

        if ($this->shouldBlockForPastEventDate()) {
            return null;
        }

        // Coverage is advisory; a contract does not require physical stock now.
        $this->validateStock();

        $event = \Illuminate\Support\Facades\DB::transaction(function () {
            $event = $this->saveEvent();
            $this->saveProductsInEvent($event);
            $this->syncEventEquipments($event);
            $this->snapshotOperationalRequirements($event);

            return $event;
        });

        return redirect()->route('events.show', $event->id);
    }

    private function snapshotOperationalRequirements(Event $event): void
    {
        if (auth()->id() && ! $event->products()->wherePivot('check_almacen', true)->exists()
            && ! $event->equipments()->wherePivot('check_almacen', true)->exists()) {
            app(\App\Services\Inventory\EventRequirementService::class)->snapshot($event, auth()->id());
            app(\App\Services\Inventory\EventCoverageService::class)->coverage($event->id);
        }
    }

    private function processDiscount(): void
    {
        $discountString = str_replace(['$', '%'], '', $this->discountString);

        if ($this->discountString == '') {
            $this->discount = 0;
        } elseif (is_numeric($discountString)) {
            $this->discount = strpos($this->discountString, '%') !== false ? $discountString / 100 : $discountString;
        } else {
            $this->discount = 0;
        }
    }

    public function closeAlert(): void
    {
        $this->showAlert = false;
        $this->enableSave = true;
        $this->errorMessage = '';
        $this->refreshPackageProducts();
    }

    public function saveAndContinue()
    {
        $this->validate();
        $this->processDiscount();

        if ($this->shouldBlockForPastEventDate()) {
            return null;
        }

        $event = \Illuminate\Support\Facades\DB::transaction(function () {
            $event = $this->saveEvent();
            $this->saveProductsInEvent($event);
            $this->syncEventEquipments($event);
            $this->snapshotOperationalRequirements($event);

            return $event;
        });
        $this->showAlert = false;

        return redirect()->route('events.show', $event->id);
    }

    public function closePastDateModal(): void
    {
        $this->showPastDateModal = false;
        $this->dispatch('closeModal', ['id' => 'past-event-date']);
    }

    public function confirmPastDateAndSave()
    {
        $this->hasConfirmedPastDate = true;
        $this->showPastDateModal = false;
        $this->dispatch('closeModal', ['id' => 'past-event-date']);

        return $this->save();
    }

    private function selectedPackageIds(): array
    {
        if ($this->packageMode !== 'registered') {
            return [];
        }

        return collect($this->package_id)
            ->filter(fn ($id) => filled($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function selectedPackageQuantities(): Collection
    {
        return collect($this->selectedPackageIds())
            ->countBy()
            ->map(fn ($quantity) => max((int) $quantity, 1));
    }

    private function selectedEmployeeIds(): array
    {
        return collect($this->employee_id)
            ->filter(fn ($id) => filled($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function selectedCustomProducts(): Collection
    {
        if ($this->packageMode !== 'custom') {
            return collect();
        }

        $rows = collect($this->customProducts)
            ->values()
            ->map(function ($row, $index) {
                $productId = (int) ($row['product_id'] ?? 0);
                $quantity = max((int) ($row['quantity'] ?? 0), 0);

                if ($productId > 0) {
                    return [
                        'group_key' => 'product-'.$productId,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'inventory_quantity_input' => $this->parseNumberInput($row['inventory_quantity'] ?? 0),
                        'inventory_price_input' => $this->parseNumberInput($row['inventory_price'] ?? 0),
                        'price_input' => $this->parseNumberInput($row['price'] ?? 0),
                        'is_new' => false,
                    ];
                }

                $name = trim((string) ($row['name'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));

                return [
                    'group_key' => 'draft-'.md5(Str::lower($name).'|'.Str::lower($unit).'|'.$index),
                    'product_id' => 0,
                    'quantity' => $quantity,
                    'is_new' => true,
                    'product_role_id' => $this->normalizeProductRoleId($row['product_role_id'] ?? 1),
                    'name' => $name,
                    'description' => trim((string) ($row['description'] ?? '')) ?: $name,
                    'unit' => $unit !== '' ? $unit : 'pz',
                    'inventory_quantity' => max($this->parseNumberInput($row['inventory_quantity'] ?? 0), (float) $quantity),
                    'inventory_price' => $this->parseNumberInput($row['inventory_price'] ?? 0),
                    'price' => $this->parseNumberInput($row['price'] ?? 0),
                ];
            })
            ->filter(function ($row) {
                if (($row['is_new'] ?? false) === true) {
                    return filled($row['name'] ?? null) && (int) ($row['quantity'] ?? 0) > 0;
                }

                return (int) ($row['product_id'] ?? 0) > 0 && (int) ($row['quantity'] ?? 0) > 0;
            });

        $inventorySnapshots = Product::with('inventories')
            ->whereIn('id', $rows->pluck('product_id')->filter(fn ($productId) => (int) $productId > 0)->all())
            ->get()
            ->mapWithKeys(fn ($product) => [
                $product->id => [
                    'inventory_price' => (float) ($product->inventories->first()?->pivot?->price ?? 0),
                    'quantity' => (float) ($product->inventories->first()?->pivot?->quantity ?? 0),
                    'exists' => $product->inventories->isNotEmpty(),
                    'name' => $product->name,
                ],
            ]);

        return $rows
            ->groupBy('group_key')
            ->map(function ($groupedRows) use ($inventorySnapshots) {
                $first = $groupedRows->first();

                if (($first['is_new'] ?? false) === true) {
                    $first['quantity'] = $groupedRows->sum('quantity');
                    $first['inventory_quantity'] = max(
                        (float) ($first['inventory_quantity'] ?? 0),
                        (float) $first['quantity']
                    );

                    return $first;
                }

                $productId = (int) ($first['product_id'] ?? 0);
                $inventoryPayload = $inventorySnapshots->get($productId, ['inventory_price' => 0, 'quantity' => 0, 'exists' => false]);
                $hasInventory = (bool) ($inventoryPayload['exists'] ?? false);
                $quantity = $groupedRows->sum('quantity');
                $inventoryQuantityInput = (float) $groupedRows
                    ->map(fn ($row) => (float) ($row['inventory_quantity_input'] ?? 0))
                    ->max();
                $inventoryPriceInput = (float) ($groupedRows
                    ->map(fn ($row) => (float) ($row['inventory_price_input'] ?? 0))
                    ->filter(fn ($price) => $price > 0)
                    ->last() ?? 0);
                $priceInput = (float) ($groupedRows
                    ->map(fn ($row) => (float) ($row['price_input'] ?? 0))
                    ->filter(fn ($price) => $price > 0)
                    ->last() ?? 0);

                return [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $priceInput,
                    'inventory_quantity' => $hasInventory
                        ? (float) ($inventoryPayload['quantity'] ?? 0)
                        : max($inventoryQuantityInput, (float) $quantity),
                    'inventory_price' => $hasInventory
                        ? (float) ($inventoryPayload['inventory_price'] ?? 0)
                        : $inventoryPriceInput,
                    'is_new' => false,
                    'name' => $inventoryPayload['name'] ?? null,
                    'requires_inventory_registration' => ! $hasInventory,
                ];
            })
            ->values();
    }

    private function customCatalogProducts(): Collection
    {
        return Product::with('inventories')
            ->where('product_role_id', '!=', 3)
            ->when($this->customProductSearch !== '', function ($query) {
                $query->where('name', 'like', '%'.trim($this->customProductSearch).'%');
            })
            ->orderBy('name', 'ASC')
            ->limit(30)
            ->get();
    }

    private function customSelectionItems(): Collection
    {
        $rows = collect($this->customProducts)->values();
        $products = Product::with('inventories')
            ->whereIn('id', $rows->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row, $index) use ($products) {
                $productId = (int) ($row['product_id'] ?? 0);
                $quantity = max((int) ($row['quantity'] ?? 1), 1);
                $product = $products->get($productId);

                if ($product) {
                    $inventoryPivot = $product->inventories->first()?->pivot;
                    $requiresInventoryRegistration = $product->inventories->isEmpty();

                    return [
                        'index' => $index,
                        'id' => $product->id,
                        'name' => $product->name,
                        'descriptor' => $this->productDescriptor($product),
                        'quantity' => $quantity,
                        'stock' => $requiresInventoryRegistration
                            ? max($this->parseNumberInput($row['inventory_quantity'] ?? 0), (float) $quantity)
                            : (float) ($inventoryPivot?->quantity ?? 0),
                        'inventory_price' => $requiresInventoryRegistration
                            ? $this->parseNumberInput($row['inventory_price'] ?? 0)
                            : (float) ($inventoryPivot?->price ?? 0),
                        'price' => $this->parseNumberInput($row['price'] ?? 0),
                        'is_new' => false,
                        'requires_inventory_registration' => $requiresInventoryRegistration,
                    ];
                }

                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'index' => $index,
                    'id' => null,
                    'name' => $name,
                    'descriptor' => $this->draftProductDescriptor($row),
                    'quantity' => $quantity,
                    'stock' => max($this->parseNumberInput($row['inventory_quantity'] ?? 0), (float) $quantity),
                    'inventory_price' => $this->parseNumberInput($row['inventory_price'] ?? 0),
                    'price' => $this->parseNumberInput($row['price'] ?? 0),
                    'is_new' => true,
                    'requires_inventory_registration' => true,
                ];
            })
            ->filter()
            ->values();
    }

    private function plannedEventProducts(): Collection
    {
        if ($this->packageMode === 'custom') {
            return $this->selectedCustomProducts();
        }

        $selectedPackageQuantities = $this->selectedPackageQuantities();

        if ($selectedPackageQuantities->isEmpty()) {
            return collect();
        }

        $packages = Package::with(['materials', 'materials.products.inventories', 'materials.inventories'])
            ->whereIn('id', $selectedPackageQuantities->keys()->all())
            ->get();

        $fixedProducts = $packages
            ->flatMap(function ($package) use ($selectedPackageQuantities) {
                $packageQuantity = max((int) ($selectedPackageQuantities->get($package->id) ?? 1), 1);

                return $package->materials
                    ->map(fn ($material) => [
                        'product_id' => $material->id,
                        'quantity' => max((int) ($material->pivot->quantity ?? 1), 1) * $packageQuantity,
                        'price' => $this->resolveProductSnapshotPrice($material),
                    ]);
            });

        $selectedVariableProducts = collect($this->radioSelected)
            ->filter(fn ($productId) => filled($productId) && (int) $productId > 0)
            ->map(fn ($productId) => [
                'product_id' => (int) $productId,
                'quantity' => 1,
                'price' => $this->resolveVariableProductSnapshotPrice((int) $productId),
            ]);

        return $fixedProducts
            ->merge($selectedVariableProducts)
            ->groupBy('product_id')
            ->map(fn ($rows, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => $rows->sum('quantity'),
                'price' => (float) ($rows->first()['price'] ?? 0),
            ])
            ->values();
    }

    private function refreshPackageProducts(): void
    {
        if ($this->packageMode !== 'registered') {
            $this->products = [];

            return;
        }

        $selectedPackageQuantities = $this->selectedPackageQuantities();

        if ($selectedPackageQuantities->isEmpty()) {
            $this->products = [];

            return;
        }

        $packages = Package::with(['materials', 'materials.products', 'materials.products.inventories', 'materials.inventories'])
            ->whereIn('id', $selectedPackageQuantities->keys()->all())
            ->get();

        $this->products = $packages
            ->flatMap(function ($package) use ($selectedPackageQuantities) {
                $packageQuantity = max((int) ($selectedPackageQuantities->get($package->id) ?? 1), 1);

                return $package->materials
                    ->where('product_role_id', 2)
                    ->values()
                    ->map(function ($material) use ($packageQuantity) {
                        $displayMaterial = clone $material;
                        $pivot = clone $material->pivot;
                        $pivot->quantity = max((int) ($pivot->quantity ?? 1), 1) * $packageQuantity;
                        $displayMaterial->setRelation('pivot', $pivot);

                        return $displayMaterial;
                    });
            })
            ->values()
            ->all();
    }

    private function productDescriptor(Product $product): string
    {
        return collect([
            $product->caliber ? $product->caliber.'"' : null,
            $product->shots ? $product->shots.' tiros' : null,
            $product->shape ?: null,
            $product->unit ?: null,
        ])->filter()->implode(' · ');
    }

    private function draftProductDescriptor(array $row): string
    {
        $roleLabel = $this->normalizeProductRoleId($row['product_role_id'] ?? 1) === 2 ? 'Material' : 'Producto';

        return collect([
            $roleLabel.' nuevo',
            filled($row['unit'] ?? null) ? (string) $row['unit'] : null,
        ])->filter()->implode(' · ');
    }

    private function sanitizeContractDescription(): ?string
    {
        $description = trim(preg_replace('/\s+/', ' ', (string) $this->contract_description) ?? '');

        return $description !== '' ? $description : null;
    }

    private function ensureCustomProductsInInventory(): void
    {
        if ($this->packageMode !== 'custom') {
            return;
        }

        $inventory = $this->resolvePrimaryInventory();

        $this->customProducts = collect($this->customProducts)
            ->values()
            ->map(function ($row) use ($inventory) {
                $productId = (int) ($row['product_id'] ?? 0);
                $quantity = max((int) ($row['quantity'] ?? 0), 1);

                if ($productId > 0) {
                    if (! $inventory->products()->where('products.id', $productId)->exists()) {
                        $location = \Illuminate\Support\Facades\DB::table('inventory_locations')->where('legacy_inventory_id', $inventory->id)->whereNotNull('adopted_at')->first();
                        if ($location) {
                            // New contracts add demand; inventory must come from a receipt or authorized adjustment.
                            $inventory->products()->attach($productId, ['quantity' => 0, 'price' => $this->parseNumberInput($row['inventory_price'] ?? 0)]);
                        } else {
                            $inventory->products()->attach($productId, [
                                'quantity' => max($this->parseNumberInput($row['inventory_quantity'] ?? 0), (float) $quantity),
                                'price' => $this->parseNumberInput($row['inventory_price'] ?? 0),
                                'check_employee' => false,
                                'check_almacen' => false,
                            ]);
                        }
                    }

                    return $row;
                }

                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    return $row;
                }

                $product = Product::create([
                    'product_role_id' => $this->normalizeProductRoleId($row['product_role_id'] ?? 1),
                    'name' => $name,
                    'description' => trim((string) ($row['description'] ?? '')) ?: $name,
                    'unit' => trim((string) ($row['unit'] ?? '')) ?: 'pz',
                    'duration' => null,
                    'shots' => null,
                    'caliber' => null,
                    'shape' => null,
                ]);

                $inventoryQuantity = max($this->parseNumberInput($row['inventory_quantity'] ?? 0), (float) $quantity);
                if (\Illuminate\Support\Facades\DB::table('inventory_locations')->where('legacy_inventory_id', $inventory->id)->whereNotNull('adopted_at')->exists()) {
                    $inventoryQuantity = 0;
                }
                $inventoryPrice = $this->parseNumberInput($row['inventory_price'] ?? 0);

                $inventory->products()->attach($product->id, [
                    'quantity' => $inventoryQuantity,
                    'price' => $inventoryPrice,
                    'check_employee' => false,
                    'check_almacen' => false,
                ]);

                $row['product_id'] = $product->id;
                $row['inventory_price'] = $inventoryPrice;
                $row['inventory_quantity'] = $inventoryQuantity;

                return $row;
            })
            ->all();
    }

    private function syncEventEquipments(Event $event): void
    {
        $payload = $this->plannedEventEquipments($event)
            ->mapWithKeys(fn ($item) => [
                $item['equipment_id'] => [
                    'quantity' => $item['quantity'],
                    'check_employee' => $this->existingEquipmentFlag($event, $item['equipment_id'], 'check_employee'),
                    'check_almacen' => $this->existingEquipmentFlag($event, $item['equipment_id'], 'check_almacen'),
                ],
            ])
            ->all();

        $event->equipments()->sync($payload);
    }

    private function plannedEventEquipments(Event $event): Collection
    {
        if ($this->packageMode !== 'registered') {
            return collect();
        }

        $selectedPackageQuantities = $this->selectedPackageQuantities();

        if ($selectedPackageQuantities->isEmpty()) {
            return collect();
        }

        return Package::with('equipments')
            ->whereIn('id', $selectedPackageQuantities->keys()->all())
            ->get()
            ->flatMap(function ($package) use ($selectedPackageQuantities) {
                $packageQuantity = max((int) ($selectedPackageQuantities->get($package->id) ?? 1), 1);

                return $package->equipments->map(fn ($equipment) => [
                    'equipment_id' => (int) $equipment->id,
                    'quantity' => max((int) ($equipment->pivot->quantity ?? 1), 1) * $packageQuantity,
                ]);
            })
            ->groupBy('equipment_id')
            ->map(fn ($rows, $equipmentId) => [
                'equipment_id' => (int) $equipmentId,
                'quantity' => $rows->sum('quantity'),
            ])
            ->values();
    }

    private function existingProductFlag(Event $event, int $productId, string $flag): bool
    {
        if (! $event->exists) {
            return false;
        }

        $existingProduct = $event->products()->where('products.id', $productId)->first();

        return (bool) ($existingProduct?->pivot?->{$flag} ?? false);
    }

    private function existingEquipmentFlag(Event $event, int $equipmentId, string $flag): bool
    {
        if (! $event->exists) {
            return false;
        }

        $existingEquipment = $event->equipments()->where('equipments.id', $equipmentId)->first();

        return (bool) ($existingEquipment?->pivot?->{$flag} ?? false);
    }

    private function resolveProductSnapshotPrice(Product $product): float
    {
        $pivotPrice = (float) ($product->pivot->price ?? 0);

        if ($pivotPrice > 0) {
            return $pivotPrice;
        }

        return (float) ($product->inventories->first()?->pivot?->price ?? 0);
    }

    private function resolveVariableProductSnapshotPrice(int $productId): float
    {
        $product = Product::with('inventories')->find($productId);

        return (float) ($product?->inventories->first()?->pivot?->price ?? 0);
    }

    private function resetNewCustomProductInputs(): void
    {
        $this->newCustomProductName = '';
        $this->newCustomProductDescription = '';
        $this->newCustomProductUnit = 'pz';
        $this->newCustomProductRoleId = 1;
        $this->newCustomProductQuantity = 1;
        $this->newCustomProductStock = '';
        $this->newCustomProductPrice = '';
        $this->newCustomProductEventPrice = '';
    }

    private function normalizeProductRoleId($value): int
    {
        return in_array((int) $value, [1, 2], true) ? (int) $value : 1;
    }

    private function parseNumberInput(mixed $value): float
    {
        $clean = preg_replace('/[^0-9\-\.,]/', '', trim((string) $value));

        if ($clean === null || $clean === '' || $clean === '-') {
            return 0.0;
        }

        return (float) str_replace(',', '', $clean);
    }

    private function resolvePrimaryInventory(): Inventory
    {
        $inventory = Inventory::find(1);

        if ($inventory) {
            return $inventory;
        }

        $inventory = new Inventory;
        $inventory->forceFill([
            'id' => 1,
            'name' => 'Polvorin 1',
            'location' => 'Rancho el Tequeque',
        ])->save();

        return $inventory->fresh();
    }

    private function shouldBlockForPastEventDate(): bool
    {
        if ($this->hasConfirmedPastDate || ! $this->eventDateIsBeforeToday()) {
            return false;
        }

        $this->showPastDateModal = true;
        $this->dispatch('openModal', ['id' => 'past-event-date']);

        return true;
    }

    private function eventDateIsBeforeToday(): bool
    {
        if (! filled($this->event_date)) {
            return false;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $this->event_date, 'America/Mexico_City')
                ->startOfDay()
                ->lt(Carbon::now('America/Mexico_City')->startOfDay());
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
