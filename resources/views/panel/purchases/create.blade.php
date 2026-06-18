@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Compras</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Compras</a></li>
                        <li class="breadcrumb-item active">Nueva compra</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $productOptions = $products ?? collect();
        $materialOptions = $materials ?? collect();
        $equipmentOptions = $equipments ?? collect();
        $catalogs = [
            'equipment' => $equipmentOptions,
            'product' => $productOptions,
            'material' => $materialOptions,
        ];
        $catalogCounts = [
            'equipment' => $equipmentOptions->count(),
            'product' => $productOptions->count(),
            'material' => $materialOptions->count(),
        ];
        $productsCount = array_sum($catalogCounts);
        $formatCurrency = fn($amount) => '$' . number_format((float) $amount, 2);
        $inputClass =
            'w-full rounded-2xl border border-primary-600/40 bg-primary-900/70 px-4 py-3 text-sm text-on-primary placeholder:text-primary-300 transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20';
        $compactInputClass =
            'w-full rounded-xl border border-primary-600/40 bg-primary-900/70 px-3 py-2 text-sm text-on-primary transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20';
        $labelClass = 'text-[11px] font-semibold uppercase tracking-[0.24em] text-primary-200';
        $sectionClass = 'rounded-3xl border border-primary-700/60 bg-primary-800 p-6 shadow-2xl shadow-primary-900/20';
        $mutedPanelClass = 'rounded-2xl border border-primary-700/60 bg-primary-900/40';

        $buildCode = fn(string $type, $id) => match ($type) {
            'equipment' => 'EQP-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'material' => 'MAT-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            default => 'PRD-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
        };
        $buildRow = function ($item, string $type, int $index) use ($buildCode) {
            $quantity = $index === 0 ? 2 : 10;
            $price = $index === 0 ? 4500 : 1200;

            return [
                'id' => $item->id,
                'type' => $type,
                'name' => $item->name,
                'code' => $buildCode($type, $item->id),
                'unit' => $item->unit ?: 'Pieza',
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $quantity * $price,
            ];
        };
        $catalogPayload = collect($catalogs)
            ->map(
                fn($items, $type) => $items
                    ->map(
                        fn($item) => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'unit' => $item->unit ?: 'Pieza',
                            'code' => $buildCode($type, $item->id),
                        ],
                    )
                    ->values(),
            )
            ->all();
        $defaultType = $equipmentOptions->isNotEmpty()
            ? 'equipment'
            : ($productOptions->isNotEmpty()
                ? 'product'
                : 'material');
        $defaultRows = ($catalogs[$defaultType] ?? collect())
            ->take(2)
            ->values()
            ->map(fn($item, $index) => $buildRow($item, $defaultType, $index));

        if ($defaultRows->isEmpty()) {
            $defaultRows = collect([
                [
                    'id' => null,
                    'type' => $defaultType,
                    'name' => 'Producto pendiente por seleccionar',
                    'code' => 'PRD-000',
                    'unit' => 'Pieza',
                    'quantity' => 1,
                    'price' => 0,
                    'subtotal' => 0,
                ],
            ]);
        }

        $oldRows = collect(old('items', []))
            ->filter(fn($row) => filled($row['item_id'] ?? null) || filled($row['quantity'] ?? null) || filled($row['price'] ?? null))
            ->values()
            ->map(function ($row) use ($catalogs, $buildCode) {
                $type = in_array($row['type'] ?? '', ['equipment', 'product', 'material'], true) ? $row['type'] : 'equipment';
                $item = ($catalogs[$type] ?? collect())->firstWhere('id', (int) ($row['item_id'] ?? 0));
                $quantity = (float) ($row['quantity'] ?? 0);
                $price = (float) ($row['price'] ?? 0);

                return [
                    'id' => $item?->id,
                    'type' => $type,
                    'name' => $item?->name ?? 'Concepto pendiente por seleccionar',
                    'code' => $item ? $buildCode($type, $item->id) : 'NUEVO',
                    'unit' => $item?->unit ?: 'Pieza',
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $quantity * $price,
                ];
            });

        $conceptRows = $oldRows->isNotEmpty() ? $oldRows : $defaultRows;
        $discountEnabled = (bool) old('discount_enabled', false);
        $discountRate = (float) old('discount_rate', 10);
        $ivaEnabled = (bool) old('iva_enabled', false);
        $ivaRate = (float) old('iva_rate', 16);
        $creditLimitEnabled = (bool) old('credit_limit_enabled', false);
        $summarySubtotal = (float) $conceptRows->sum('subtotal');
        $summaryDiscount = $discountEnabled ? $summarySubtotal * ($discountRate / 100) : 0;
        $summaryTax = $ivaEnabled ? ($summarySubtotal - $summaryDiscount) * ($ivaRate / 100) : 0;
        $summaryTotal = $summarySubtotal - $summaryDiscount + $summaryTax;
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.9fr)]">
            <div class="relative overflow-hidden rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div class="absolute -left-12 top-8 h-44 w-44 rounded-full bg-secondary/10 blur-3xl"></div>
                <div class="absolute -right-10 bottom-0 h-52 w-52 rounded-full bg-accent/10 blur-3xl"></div>

                <div class="relative flex h-full flex-col justify-between gap-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            Centro de Compras
                        </span>
                        <span
                            class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-secondary">
                            Nueva captura
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-secondary">Abastecimiento operativo
                        </p>
                        <h2 class="mt-4 max-w-4xl text-4xl font-bold tracking-tight text-on-primary xl:text-5xl">
                            Registrar compra para actualizar el almacen.
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                            Captura proveedor, fecha, comprobantes y conceptos para mantener el historial de adquisiciones
                            alineado con el inventario de Pirotecnia San Rafael.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Folio</p>
                            <p class="mt-3 text-2xl font-bold text-on-primary">CMP-NUEVA</p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Conceptos</p>
                            <p class="mt-3 text-2xl font-bold text-on-primary">{{ number_format($productsCount) }}</p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Estatus</p>
                            <p class="mt-3 text-2xl font-bold text-secondary">Borrador</p>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Checklist de compra</p>
                <h3 class="mt-3 text-3xl font-bold text-on-primary">Datos necesarios</h3>
                <p class="mt-2 text-sm leading-6 text-primary-100">
                    La compra queda clara cuando el folio, proveedor, fecha, conceptos y comprobantes se capturan en el mismo
                    flujo.
                </p>

                <div class="mt-8 space-y-3">
                    <div class="rounded-2xl bg-on-primary/10 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Catalogo disponible
                        </p>
                        <p class="mt-2 text-sm font-semibold text-on-primary">{{ number_format($productsCount) }} conceptos
                            para seleccionar</p>
                    </div>
                    <div class="rounded-2xl bg-on-primary/10 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Validacion visual
                        </p>
                        <p class="mt-2 text-sm font-semibold text-on-primary">Detalle, totales y documentos en pantalla</p>
                    </div>
                    <div class="rounded-2xl bg-on-primary/10 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Salida esperada</p>
                        <p class="mt-2 text-sm font-semibold text-on-primary">Movimiento listo para inventario</p>
                    </div>
                </div>
            </aside>
        </section>

        @if ($errors->any())
            <section class="rounded-3xl border border-error/30 bg-error/10 p-5 shadow-soft">
                <p class="text-sm font-semibold text-error">No se pudo guardar la compra</p>
                <ul class="mt-3 space-y-2 text-sm text-primary-100">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form action="{{ route('purchases.store') }}" class="space-y-8" method="POST">
            @csrf
            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.8fr)]">
                <div class="{{ $sectionClass }}">
                    <div
                        class="mb-6 flex flex-col gap-3 border-b border-primary-700/60 pb-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Datos generales</p>
                            <h3 class="mt-2 text-2xl font-bold text-on-primary">Informacion de la compra</h3>
                        </div>
                        <p class="max-w-xl text-sm leading-6 text-primary-200">
                            Registra la informacion base para ubicar la adquisicion dentro del historial y asociarla con el
                            usuario responsable.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <div class="space-y-2">
                            <label class="{{ $labelClass }}" for="folio">Folio de compra</label>
                            <input class="{{ $inputClass }} font-mono text-secondary" disabled id="folio" type="text"
                                value="CMP-NUEVA">
                        </div>

                        <div class="space-y-2">
                            <label class="{{ $labelClass }}" for="date">Fecha de registro</label>
                            <input class="{{ $inputClass }}" id="date" name="date" type="date"
                                value="{{ old('date', now()->format('Y-m-d')) }}">
                        </div>

                        <div class="space-y-2">
                            <label class="{{ $labelClass }}">Estatus</label>
                            <div class="flex min-h-12 items-center">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-secondary">
                                    <span class="h-1.5 w-1.5 rounded-full bg-secondary"></span>
                                    Borrador
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="{{ $labelClass }}" for="supplier">Proveedor</label>
                            <select class="{{ $inputClass }} appearance-none" id="supplier" name="supplier_name">
                                <option value="">Seleccionar proveedor...</option>
                                <option value="Industrial Colima S.A. de C.V." @selected(old('supplier_name') === 'Industrial Colima S.A. de C.V.')>
                                    Industrial Colima S.A. de C.V.
                                </option>
                                <option value="Quimicos del Pacifico" @selected(old('supplier_name') === 'Quimicos del Pacifico')>Quimicos del Pacifico</option>
                                <option value="Suministros Pirotecnicos Global" @selected(old('supplier_name') === 'Suministros Pirotecnicos Global')>
                                    Suministros Pirotecnicos Global
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="{{ $labelClass }}" for="payment_method">Metodo de pago</label>
                            <select class="{{ $inputClass }} appearance-none" id="payment_method" name="payment_method">
                                <option value="Transferencia" @selected(old('payment_method', 'Transferencia') === 'Transferencia')>Transferencia</option>
                                <option value="Efectivo" @selected(old('payment_method') === 'Efectivo')>Efectivo</option>
                                <option value="Credito" @selected(old('payment_method') === 'Credito')>Credito</option>
                                <option value="Cheque" @selected(old('payment_method') === 'Cheque')>Cheque</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="{{ $labelClass }}" for="reference">Referencia / factura</label>
                            <input class="{{ $inputClass }}" id="reference" name="reference" placeholder="F-2026-X"
                                type="text" value="{{ old('reference') }}">
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="{{ $labelClass }}">Usuario que registra</label>
                            <div class="flex items-center gap-3 rounded-2xl border border-primary-700/60 bg-primary-900/40 px-4 py-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-primary-600/40 bg-primary-700 text-on-primary">
                                    <span class="material-symbols-outlined text-lg">shield_person</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-on-primary">Administrador</p>
                                    <p class="text-xs text-primary-200">Sede Central</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-6">
                    <section class="{{ $sectionClass }} border-t-4 border-t-secondary">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Resumen economico</p>
                        <h3 class="mt-2 text-2xl font-bold text-on-primary">Totales estimados</h3>

                        <div class="mt-8 space-y-4">
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-primary-200">Subtotal</span>
                                <span class="font-mono font-semibold text-on-primary" id="purchase-subtotal">{{ $formatCurrency($summarySubtotal) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <label class="flex items-center gap-2 text-primary-200" for="discount_enabled">
                                    <input class="rounded border-primary-600 bg-primary-900 text-secondary focus:ring-secondary/30"
                                        id="discount_enabled" name="discount_enabled" type="checkbox" value="1"
                                        @checked($discountEnabled)>
                                    Descuento
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        class="w-20 rounded-xl border border-primary-600/40 bg-primary-900/70 px-3 py-2 text-right text-sm text-on-primary transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
                                        id="discount_rate" min="0" name="discount_rate" step="0.01" type="number"
                                        value="{{ old('discount_rate', 10) }}">
                                    <span class="text-xs font-semibold text-primary-200">%</span>
                                    <span class="font-mono font-semibold text-error" id="purchase-discount">-{{ $formatCurrency($summaryDiscount) }}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <label class="flex items-center gap-2 text-primary-200" for="iva_enabled">
                                    <input class="rounded border-primary-600 bg-primary-900 text-secondary focus:ring-secondary/30"
                                        id="iva_enabled" name="iva_enabled" type="checkbox" value="1" @checked($ivaEnabled)>
                                    IVA
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        class="w-20 rounded-xl border border-primary-600/40 bg-primary-900/70 px-3 py-2 text-right text-sm text-on-primary transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
                                        id="iva_rate" min="0" name="iva_rate" step="0.01" type="number"
                                        value="{{ old('iva_rate', 16) }}">
                                    <span class="text-xs font-semibold text-primary-200">%</span>
                                    <span class="font-mono font-semibold text-on-primary" id="purchase-tax">{{ $formatCurrency($summaryTax) }}</span>
                                </div>
                            </div>

                            <div class="spark-divider my-6"></div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Total final
                                </p>
                                <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                                    <span class="text-4xl font-bold tracking-tight text-on-primary" id="purchase-total">
                                        {{ $formatCurrency($summaryTotal) }}
                                    </span>
                                    <span
                                        class="rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-accent">
                                        MXN
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="{{ $sectionClass }}">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Indicadores</p>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="{{ $mutedPanelClass }} p-4">
                                <label class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200"
                                    for="credit_limit_enabled">
                                    <input class="rounded border-primary-600 bg-primary-900 text-secondary focus:ring-secondary/30"
                                        id="credit_limit_enabled" name="credit_limit_enabled" type="checkbox" value="1"
                                        @checked($creditLimitEnabled)>
                                    Limite de credito
                                </label>
                                <input
                                    class="{{ $compactInputClass }} mt-3 font-mono"
                                    id="credit_limit_amount" min="0" name="credit_limit_amount" placeholder="$0.00"
                                    step="0.01" type="number" value="{{ old('credit_limit_amount') }}">
                                <p class="mt-2 text-xs text-primary-200">Se captura solo si el proveedor maneja credito.</p>
                            </div>
                            <div class="{{ $mutedPanelClass }} p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Ultima
                                    compra</p>
                                <p class="mt-2 text-sm font-bold text-on-primary">12 Oct 2023</p>
                                <p class="mt-1 font-mono text-xs font-semibold text-accent">+$12,400.00</p>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>

            <section class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-3" id="purchase-type-switch">
                        <button
                            class="group flex items-center justify-center gap-4 rounded-3xl border-2 border-secondary/40 bg-primary-800 p-6 text-left shadow-2xl shadow-primary-900/20 transition-all hover:border-secondary/70"
                            data-purchase-type="equipment" type="button">
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/10 text-secondary transition-transform group-hover:scale-105">
                                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">construction</span>
                            </span>
                            <span>
                                <span class="block font-bold text-on-primary">Equipo</span>
                                <span class="mt-1 block text-xs uppercase tracking-[0.18em] text-primary-200">Maquinaria y
                                    herramientas</span>
                                <span class="mt-2 block text-xs font-semibold text-secondary">{{ $catalogCounts['equipment'] }} disponibles</span>
                            </span>
                        </button>

                        <button
                            class="group flex items-center justify-center gap-4 rounded-3xl border-2 border-primary-700/60 bg-primary-900/40 p-6 text-left transition-all hover:border-accent/50"
                            data-purchase-type="product" type="button">
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent/10 text-accent transition-transform group-hover:scale-105">
                                <span class="material-symbols-outlined">inventory_2</span>
                            </span>
                            <span>
                                <span class="block font-bold text-primary-100">Productos</span>
                                <span class="mt-1 block text-xs uppercase tracking-[0.18em] text-primary-200">Catalogo operativo</span>
                                <span class="mt-2 block text-xs font-semibold text-accent">{{ $catalogCounts['product'] }} disponibles</span>
                            </span>
                        </button>

                        <button
                            class="group flex items-center justify-center gap-4 rounded-3xl border-2 border-primary-700/60 bg-primary-900/40 p-6 text-left transition-all hover:border-warning/50"
                            data-purchase-type="material" type="button">
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-warning/10 text-warning transition-transform group-hover:scale-105">
                                <span class="material-symbols-outlined">science</span>
                            </span>
                            <span>
                                <span class="block font-bold text-primary-100">Material</span>
                                <span class="mt-1 block text-xs uppercase tracking-[0.18em] text-primary-200">Quimicos e insumos</span>
                                <span class="mt-2 block text-xs font-semibold text-warning">{{ $catalogCounts['material'] }} disponibles</span>
                            </span>
                        </button>
                    </div>

                    <section class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/20">
                        <div
                            class="flex flex-col gap-4 border-b border-primary-700/60 px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Detalle de
                                    conceptos</p>
                                <h3 class="mt-2 text-2xl font-bold text-on-primary" id="purchase-detail-title">Equipo</h3>
                            </div>
                            <button
                                class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
                                id="add-purchase-row" type="button" @disabled($productsCount === 0)>
                                <span class="material-symbols-outlined text-lg">add_circle</span>
                                Agregar concepto
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="responsive-stack-table responsive-stack-table-dark min-w-full text-left">
                                <thead class="bg-primary-700/60">
                                    <tr>
                                        <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                            Concepto</th>
                                        <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                            Cantidad</th>
                                        <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                            Unidad</th>
                                        <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                            Costo unit.</th>
                                        <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                            Subtotal</th>
                                        <th class="px-6 py-4"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-primary-700/60" id="purchase-items-body">
                                    @foreach ($conceptRows as $index => $row)
                                        @php
                                            $rowOptions = $catalogs[$row['type']] ?? collect();
                                        @endphp
                                        <tr class="transition-colors hover:bg-primary-700/35" data-purchase-row>
                                            <td class="px-6 py-5" data-label="Concepto">
                                                <input data-row-type name="items[{{ $index }}][type]" type="hidden"
                                                    value="{{ $row['type'] }}">
                                                @if ($rowOptions->isNotEmpty())
                                                    <select class="{{ $compactInputClass }} appearance-none" data-item-select
                                                        name="items[{{ $index }}][item_id]" required>
                                                        <option value="">Seleccionar concepto...</option>
                                                        @foreach ($rowOptions as $item)
                                                            <option data-code="{{ $buildCode($row['type'], $item->id) }}"
                                                                data-unit="{{ $item->unit ?: 'Pieza' }}" value="{{ $item->id }}"
                                                                @selected($item->id === $row['id'])>
                                                                {{ $item->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <p class="text-sm font-semibold text-on-primary">{{ $row['name'] }}</p>
                                                @endif
                                                <p class="mt-2 text-xs text-primary-200" data-code-label>{{ $row['code'] }}</p>
                                            </td>
                                            <td class="px-6 py-5" data-label="Cantidad">
                                                <input class="{{ $compactInputClass }} max-w-24 text-center"
                                                    data-quantity-input min="0.01" name="items[{{ $index }}][quantity]"
                                                    required step="0.01" type="number" value="{{ $row['quantity'] }}">
                                            </td>
                                            <td class="px-6 py-5 text-sm text-primary-100" data-label="Unidad" data-unit-label>
                                                {{ $row['unit'] }}
                                            </td>
                                            <td class="px-6 py-5 min-w-44" data-label="Costo unit.">
                                                <input class="{{ $compactInputClass }} min-w-36 font-mono"
                                                    data-price-input min="0" name="items[{{ $index }}][price]" required
                                                    step="0.01" type="number" value="{{ $row['price'] }}">
                                            </td>
                                            <td class="px-6 py-5 font-mono text-sm font-bold text-secondary" data-label="Subtotal" data-row-subtotal>
                                                {{ $formatCurrency($row['subtotal']) }}
                                            </td>
                                            <td class="px-6 py-5 text-right" data-label="Acciones">
                                                <button
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-primary-200 transition-colors hover:bg-error/10 hover:text-error"
                                                    data-remove-row type="button">
                                                    <span class="material-symbols-outlined text-lg">delete</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <template id="purchase-row-template">
                            <tr class="transition-colors hover:bg-primary-700/35" data-purchase-row>
                                <td class="px-6 py-5" data-label="Concepto">
                                    <input data-row-type name="items[__INDEX__][type]" type="hidden" value="__TYPE__">
                                    <select class="{{ $compactInputClass }} appearance-none" data-item-select
                                        name="items[__INDEX__][item_id]" required>
                                        <option value="">Seleccionar concepto...</option>
                                    </select>
                                    <p class="mt-2 text-xs text-primary-200" data-code-label>NUEVO</p>
                                </td>
                                <td class="px-6 py-5" data-label="Cantidad">
                                    <input class="{{ $compactInputClass }} max-w-24 text-center" data-quantity-input
                                        min="0.01" name="items[__INDEX__][quantity]" required step="0.01" type="number"
                                        value="1">
                                </td>
                                <td class="px-6 py-5 text-sm text-primary-100" data-label="Unidad" data-unit-label>
                                    Pieza
                                </td>
                                <td class="px-6 py-5 min-w-44" data-label="Costo unit.">
                                    <input class="{{ $compactInputClass }} min-w-36 font-mono" data-price-input min="0"
                                        name="items[__INDEX__][price]" required step="0.01" type="number" value="0">
                                </td>
                                <td class="px-6 py-5 font-mono text-sm font-bold text-secondary" data-label="Subtotal"
                                    data-row-subtotal>
                                    $0.00
                                </td>
                                <td class="px-6 py-5 text-right" data-label="Acciones">
                                    <button
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-primary-200 transition-colors hover:bg-error/10 hover:text-error"
                                        data-remove-row type="button">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </section>
            </section>

            <section
                class="flex flex-col gap-3 rounded-3xl border border-primary-700/60 bg-primary-800 p-6 shadow-soft sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-on-primary">Compra lista para revision</p>
                    <p class="mt-1 text-sm text-primary-200">
                        Revisa proveedor, productos y totales antes de confirmar el movimiento.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('purchases.index') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-5 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600">
                        <span class="material-symbols-outlined text-base">close</span>
                        Cancelar
                    </a>
                    <button
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-5 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                        name="status" type="submit" value="draft">
                        <span class="material-symbols-outlined text-base">draft</span>
                        Guardar borrador
                    </button>
                    <button
                        class="inline-flex items-center gap-2 rounded-xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-95"
                        name="status" type="submit" value="confirmed">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">save</span>
                        Guardar compra
                    </button>
                </div>
            </section>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const body = document.getElementById('purchase-items-body');
                const template = document.getElementById('purchase-row-template');
                const addButton = document.getElementById('add-purchase-row');
                const detailTitle = document.getElementById('purchase-detail-title');
                const typeButtons = Array.from(document.querySelectorAll('[data-purchase-type]'));
                const discountEnabled = document.getElementById('discount_enabled');
                const discountRate = document.getElementById('discount_rate');
                const ivaEnabled = document.getElementById('iva_enabled');
                const ivaRate = document.getElementById('iva_rate');
                const creditLimitEnabled = document.getElementById('credit_limit_enabled');
                const creditLimitAmount = document.getElementById('credit_limit_amount');
                const catalogs = @json($catalogPayload);
                const initialType = @json($defaultType);
                const typeLabels = {
                    equipment: 'Equipo',
                    product: 'Productos',
                    material: 'Material',
                };

                if (!body || !template || !addButton) {
                    return;
                }

                const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', {
                    currency: 'MXN',
                    style: 'currency',
                }).format(Number.isFinite(amount) ? amount : 0);

                const rows = () => Array.from(body.querySelectorAll('[data-purchase-row]'));
                let activeType = 'equipment';

                const setOptionalState = () => {
                    if (discountRate) {
                        discountRate.disabled = !discountEnabled?.checked;
                    }

                    if (ivaRate) {
                        ivaRate.disabled = !ivaEnabled?.checked;
                    }

                    if (creditLimitAmount) {
                        creditLimitAmount.disabled = !creditLimitEnabled?.checked;
                    }
                };

                const fillOptions = (select, type) => {
                    const options = catalogs[type] || [];
                    select.innerHTML = '<option value="">Seleccionar concepto...</option>';

                    options.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.dataset.unit = item.unit;
                        option.dataset.code = item.code;
                        option.textContent = item.name;
                        select.appendChild(option);
                    });
                };

                const applyActiveType = (type) => {
                    activeType = type;

                    typeButtons.forEach((button) => {
                        const isActive = button.dataset.purchaseType === type;
                        button.classList.toggle('border-secondary/40', isActive);
                        button.classList.toggle('bg-primary-800', isActive);
                        button.classList.toggle('shadow-2xl', isActive);
                        button.classList.toggle('border-primary-700/60', !isActive);
                        button.classList.toggle('bg-primary-900/40', !isActive);
                    });

                    if (detailTitle) {
                        detailTitle.textContent = typeLabels[type] || 'Conceptos';
                    }

                    addButton.disabled = (catalogs[type] || []).length === 0;
                };

                const updateRow = (row) => {
                    const quantity = parseFloat(row.querySelector('[data-quantity-input]')?.value || '0');
                    const price = parseFloat(row.querySelector('[data-price-input]')?.value || '0');
                    const subtotal = quantity * price;
                    const subtotalTarget = row.querySelector('[data-row-subtotal]');
                    const unitTarget = row.querySelector('[data-unit-label]');
                    const codeTarget = row.querySelector('[data-code-label]');
                    const selectedItem = row.querySelector('[data-item-select] option:checked');

                    if (subtotalTarget) {
                        subtotalTarget.textContent = formatCurrency(subtotal);
                    }

                    if (unitTarget && selectedItem?.dataset.unit) {
                        unitTarget.textContent = selectedItem.dataset.unit;
                    }

                    if (codeTarget && selectedItem?.dataset.code) {
                        codeTarget.textContent = selectedItem.dataset.code;
                    }

                    return subtotal;
                };

                const updateTotals = () => {
                    const subtotal = rows().reduce((total, row) => total + updateRow(row), 0);
                    const discount = discountEnabled?.checked ? subtotal * (parseFloat(discountRate?.value || '0') / 100) : 0;
                    const tax = ivaEnabled?.checked ? (subtotal - discount) * (parseFloat(ivaRate?.value || '0') / 100) : 0;
                    const total = subtotal - discount + tax;

                    document.getElementById('purchase-subtotal').textContent = formatCurrency(subtotal);
                    document.getElementById('purchase-discount').textContent = `-${formatCurrency(discount)}`;
                    document.getElementById('purchase-tax').textContent = formatCurrency(tax);
                    document.getElementById('purchase-total').textContent = formatCurrency(total);
                };

                let nextIndex = rows().length;

                addButton.addEventListener('click', () => {
                    body.insertAdjacentHTML(
                        'beforeend',
                        template.innerHTML.replaceAll('__INDEX__', nextIndex).replaceAll('__TYPE__', activeType),
                    );
                    const row = rows().at(-1);
                    const select = row.querySelector('[data-item-select]');

                    if (select) {
                        fillOptions(select, activeType);
                    }

                    nextIndex += 1;
                    updateTotals();
                });

                body.addEventListener('input', (event) => {
                    if (event.target.matches('[data-quantity-input], [data-price-input]')) {
                        updateTotals();
                    }
                });

                body.addEventListener('change', (event) => {
                    if (event.target.matches('[data-item-select]')) {
                        updateTotals();
                    }
                });

                body.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-remove-row]');

                    if (!removeButton || rows().length <= 1) {
                        return;
                    }

                    removeButton.closest('[data-purchase-row]').remove();
                    updateTotals();
                });

                typeButtons.forEach((button) => {
                    button.addEventListener('click', () => applyActiveType(button.dataset.purchaseType));
                });

                [discountEnabled, discountRate, ivaEnabled, ivaRate].forEach((control) => {
                    control?.addEventListener('input', () => {
                        setOptionalState();
                        updateTotals();
                    });
                    control?.addEventListener('change', () => {
                        setOptionalState();
                        updateTotals();
                    });
                });

                creditLimitEnabled?.addEventListener('change', setOptionalState);
                setOptionalState();
                applyActiveType(initialType);
                updateTotals();
            });
        </script>
    </div>
@endsection
