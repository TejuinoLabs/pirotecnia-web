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
                        <li class="breadcrumb-item active">Compras</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $formatCurrency = fn($amount) => '$' . number_format((float) $amount, 2);
        $formatQuantity = function ($amount) {
            $amount = (float) $amount;

            return fmod($amount, 1.0) === 0.0 ? number_format($amount, 0) : number_format($amount, 2);
        };

        $purchaseRows = $purchases
            ->map(function ($purchase) {
                $productItems = $purchase->products
                    ->map(function ($product) {
                        $quantity = (float) ($product->pivot->quantity ?? 0);
                        $price = (float) ($product->pivot->price ?? 0);

                        return [
                            'name' => $product->name,
                            'quantity' => $quantity,
                            'price' => $price,
                            'total' => $quantity * $price,
                        ];
                    })
                    ->values();
                $equipmentItems = $purchase->equipments
                    ->map(function ($equipment) {
                        $quantity = (float) ($equipment->pivot->quantity ?? 0);
                        $price = (float) ($equipment->pivot->price ?? 0);
                        return [
                            'name' => $equipment->name,
                            'quantity' => $quantity,
                            'price' => $price,
                            'total' => $quantity * $price,
                        ];
                    })
                    ->values();

                $lineItems = collect($productItems->all())->merge($equipmentItems)->values();

                $summary = $lineItems
                    ->take(2)
                    ->map(function ($item) {
                        return $item['name'] .
                            ' x' .
                            ((float) $item['quantity'] === floor((float) $item['quantity'])
                                ? number_format((float) $item['quantity'], 0)
                                : number_format((float) $item['quantity'], 2));
                    })
                    ->implode(', ');
                $subtotal = (float) $lineItems->sum('total');
                $discount = (bool) ($purchase->discount_enabled ?? false)
                    ? $subtotal * ((float) ($purchase->discount_rate ?? 0) / 100)
                    : 0;
                $tax = (bool) ($purchase->iva_enabled ?? false)
                    ? ($subtotal - $discount) * ((float) ($purchase->iva_rate ?? 0) / 100)
                    : 0;

                return [
                    'id' => $purchase->id,
                    'folio' => 'CMP-' . str_pad((string) $purchase->id, 4, '0', STR_PAD_LEFT),
                    'date' => \Illuminate\Support\Carbon::parse($purchase->date),
                    'line_items' => $lineItems,
                    'product_count' => $lineItems->count(),
                    'units' => (float) $lineItems->sum('quantity'),
                    'total' => max($subtotal - $discount + $tax, 0),
                    'summary' => $summary !== '' ? $summary : 'Sin conceptos registrados',
                    'remaining_products' => max($lineItems->count() - 2, 0),
                ];
            })
            ->values();

        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $currentMonthPurchases = $purchaseRows->filter(
            fn($purchase) => $purchase['date']->between($currentMonthStart, $currentMonthEnd),
        );
        $groupedProducts = $purchaseRows
            ->flatMap(fn($purchase) => $purchase['line_items'])
            ->groupBy('name')
            ->map(function ($items, $name) {
                return [
                    'name' => $name,
                    'quantity' => (float) $items->sum('quantity'),
                    'total' => (float) $items->sum('total'),
                ];
            })
            ->values();
        $distinctProductCount = $groupedProducts->count();
        $topProducts = $groupedProducts->sortByDesc('quantity')->take(4)->values();

        $latestPurchase = $purchaseRows->first();
        $totals = [
            'count' => $purchaseRows->count(),
            'month_count' => $currentMonthPurchases->count(),
            'month_spend' => (float) $currentMonthPurchases->sum('total'),
            'total_spend' => (float) $purchaseRows->sum('total'),
            'units' => (float) $purchaseRows->sum('units'),
            'avg_ticket' => $purchaseRows->isNotEmpty() ? (float) $purchaseRows->avg('total') : 0,
        ];

        $kpis = [
            [
                'label' => 'Compras del mes',
                'value' => number_format($totals['month_count']),
                'helper' => $formatCurrency($totals['month_spend']) . ' comprometidos este mes',
                'icon' => 'shopping_cart',
                'tone' => 'secondary',
            ],
            [
                'label' => 'Gasto acumulado',
                'value' => $formatCurrency($totals['total_spend']),
                'helper' => number_format($totals['count']) . ' compras registradas en el historico',
                'icon' => 'payments',
                'tone' => 'accent',
            ],
            [
                'label' => 'Unidades adquiridas',
                'value' => $formatQuantity($totals['units']),
                'helper' => 'Suma de productos y materiales comprados',
                'icon' => 'inventory_2',
                'tone' => 'warning',
            ],
            [
                'label' => 'Ticket promedio',
                'value' => $formatCurrency($totals['avg_ticket']),
                'helper' => $latestPurchase
                    ? 'Ultima compra: ' . $latestPurchase['date']->format('d M Y')
                    : 'Sin registros para calcular promedio',
                'icon' => 'monitoring',
                'tone' => 'secondary',
            ],
        ];

        $toneClasses = [
            'secondary' => 'border-secondary/30 bg-secondary/10 text-secondary',
            'accent' => 'border-accent/30 bg-accent/10 text-accent',
            'warning' => 'border-warning/30 bg-warning/10 text-warning',
            'error' => 'border-error/30 bg-error/10 text-error',
        ];

        $toneIconClasses = [
            'secondary' => 'text-secondary',
            'accent' => 'text-accent',
            'warning' => 'text-warning',
            'error' => 'text-error',
        ];
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.82fr)]">
            <div class="relative overflow-hidden rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div class="absolute -left-12 top-8 h-44 w-44 rounded-full bg-secondary/15 blur-3xl"></div>
                <div class="absolute -right-10 bottom-0 h-52 w-52 rounded-full bg-accent/15 blur-3xl"></div>

                <div class="relative flex h-full flex-col justify-between gap-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            Centro de Compras
                        </span>
                        <span
                            class="inline-flex rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-warning">
                            {{ number_format($totals['count']) }} movimientos registrados
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-secondary">Abastecimiento Operativo
                        </p>
                        <h2 class="mt-4 font-heading text-4xl font-bold tracking-tight text-on-primary xl:text-5xl">
                            Historial, volumen y detalle de compras del almacen.
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                            Consulta el gasto acumulado, los productos adquiridos y el detalle reciente de cada compra sin
                            salir del panel operativo.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-secondary">calendar_month</span>
                                {{ number_format($totals['month_count']) }} compras durante el mes actual
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-accent">payments</span>
                                {{ $formatCurrency($totals['total_spend']) }} de gasto historico
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-warning">inventory_2</span>
                                {{ $formatQuantity($totals['units']) }} unidades incorporadas
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Ticket
                                promedio</p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">{{ $formatCurrency($totals['avg_ticket']) }}
                            </p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Productos
                                distintos
                            </p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">{{ $distinctProductCount }}</p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Ultimo
                                registro</p>
                            <p class="mt-3 text-3xl font-bold text-secondary">
                                {{ $latestPurchase ? $latestPurchase['date']->format('d M') : '--' }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Centro de Acciones</p>
                <h3 class="mt-3 text-3xl font-bold text-on-primary">Gestion de compras</h3>
                <p class="mt-2 text-sm text-primary-100">
                    Accesos directos para registrar adquisiciones nuevas, revisar inventario y administrar el catalogo.
                </p>

                <div class="mt-8 grid gap-3">
                    <a href="{{ route('purchases.create') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-secondary px-5 py-4 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-[0.99]">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined">add_shopping_cart</span>
                            Registrar compra
                        </span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>

                    <a href="{{ route('inventories.index') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-warning">warehouse</span>
                            Revisar inventario
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>

                    <a href="{{ route('settings.products.index') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-accent">inventory</span>
                            Catalogo de productos
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>
                </div>

                <div class="mt-8 rounded-2xl bg-on-primary/10 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Productos con mayor
                        compra
                    </p>

                    <div class="mt-4 space-y-3">
                        @forelse ($topProducts as $product)
                            <div class="rounded-2xl bg-primary-800/40 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-on-primary">{{ $product['name'] }}</span>
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] {{ $toneClasses[$loop->first ? 'secondary' : ($loop->index === 1 ? 'accent' : 'warning')] }}">
                                        {{ $formatQuantity($product['quantity']) }} u
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-primary-200">
                                    {{ $formatCurrency($product['total']) }} acumulados en historial de compras.
                                </p>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-primary-800/40 p-4">
                                <p class="text-sm font-semibold text-on-primary">Sin productos registrados</p>
                                <p class="mt-1 text-xs text-primary-200">Las compras apareceran aqui conforme se den de
                                    alta.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </section>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <article class="relative overflow-hidden rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <div class="absolute right-4 top-4 opacity-15">
                        <span class="material-symbols-outlined text-6xl {{ $toneIconClasses[$kpi['tone']] }}">
                            {{ $kpi['icon'] }}
                        </span>
                    </div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">{{ $kpi['label'] }}
                    </p>
                    <p class="mt-4 text-4xl font-bold text-on-primary">{{ $kpi['value'] }}</p>
                    <p class="mt-4 text-sm text-primary-200">{{ $kpi['helper'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.85fr)]">
            <section class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/10">
                <div
                    class="flex flex-col gap-4 border-b border-border px-6 py-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-secondary">Historial de Compras</p>
                        <h3 class="mt-3 text-2xl font-bold text-on-primary">Adquisiciones registradas</h3>
                        <p class="mt-2 text-sm text-primary-200">
                            Monto total, unidades y resumen de productos incorporados por compra.
                        </p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-surface-alt px-4 py-2 text-sm text-muted">
                        <span class="material-symbols-outlined text-accent">receipt_long</span>
                        {{ number_format($totals['count']) }} filas visibles
                    </div>
                </div>

                @if ($purchaseRows->isEmpty())
                    <div class="p-10">
                        <div class="rounded-3xl border border-border bg-surface-alt p-8 text-center">
                            <span class="material-symbols-outlined text-5xl text-primary-300">shopping_cart</span>
                            <p class="mt-4 text-lg font-bold text-on-surface">Sin compras registradas</p>
                            <p class="mt-2 text-sm text-muted">
                                Registra la primera compra para comenzar a ver gasto, productos adquiridos y resumen
                                operativo.
                            </p>
                            <a href="{{ route('purchases.create') }}"
                                class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600">
                                <span class="material-symbols-outlined">add_shopping_cart</span>
                                Registrar compra
                            </a>
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="responsive-stack-table responsive-stack-table-light min-w-full text-left">
                            <thead class="bg-surface-alt">
                                <tr>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Folio</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Fecha</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Detalle
                                    </th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Productos
                                    </th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Unidades
                                    </th>
                                    <th
                                        class="px-6 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Monto
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($purchaseRows as $purchase)
                                    <tr class="transition-colors hover:bg-surface-alt">
                                        <td class="px-6 py-5" data-label="Folio">
                                            <div class="font-mono text-sm font-semibold text-primary">
                                                {{ $purchase['folio'] }}</div>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-muted" data-label="Fecha">
                                            {{ $purchase['date']->format('d/m/Y') }}
                                        </td>
                                        <td class="px-6 py-5" data-label="Detalle">
                                            <p class="text-sm font-semibold text-on-surface">{{ $purchase['summary'] }}
                                            </p>
                                            @if ($purchase['remaining_products'] > 0)
                                                <p class="mt-1 text-xs text-muted">
                                                    +{{ $purchase['remaining_products'] }} productos adicionales en esta
                                                    compra
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-5" data-label="Productos">
                                            <span
                                                class="inline-flex rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-semibold text-accent">
                                                {{ number_format($purchase['product_count']) }} registros
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-sm font-semibold text-on-surface" data-label="Unidades">
                                            {{ $formatQuantity($purchase['units']) }}
                                        </td>
                                        <td class="px-6 py-5 text-right text-sm font-bold text-on-surface"
                                            data-label="Monto">
                                            {{ $formatCurrency($purchase['total']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <aside class="space-y-6">
                <section class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Detalle destacado
                            </p>
                            <h3 class="mt-3 text-2xl font-bold text-on-primary">
                                {{ $latestPurchase ? $latestPurchase['folio'] : 'Sin compras' }}
                            </h3>
                            <p class="mt-2 text-sm text-primary-200">
                                {{ $latestPurchase ? 'Ultima compra registrada en el sistema.' : 'No hay movimientos para mostrar.' }}
                            </p>
                        </div>
                        <span
                            class="inline-flex rounded-full border border-primary-600/50 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            {{ $latestPurchase ? $latestPurchase['date']->format('d M Y') : '--' }}
                        </span>
                    </div>

                    @if ($latestPurchase)
                        <div class="mt-6 space-y-4">
                            <div class="rounded-2xl bg-primary-700/60 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-primary-200">Monto total</span>
                                    <span
                                        class="text-lg font-bold text-on-primary">{{ $formatCurrency($latestPurchase['total']) }}</span>
                                </div>
                                <div class="mt-3 spark-divider"></div>
                                <div class="mt-3 flex items-center justify-between gap-4">
                                    <span class="text-sm text-primary-200">Unidades adquiridas</span>
                                    <span class="text-sm font-semibold text-secondary">
                                        {{ $formatQuantity($latestPurchase['units']) }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-primary-700/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Desglose
                                    de productos
                                </p>
                                <div class="mt-4 space-y-3">
                                    @foreach ($latestPurchase['line_items']->take(5) as $item)
                                        <div class="flex items-start justify-between gap-4 text-sm">
                                            <div>
                                                <p class="font-semibold text-on-primary">{{ $item['name'] }}</p>
                                                <p class="mt-1 text-xs text-primary-200">
                                                    {{ $formatQuantity($item['quantity']) }} x
                                                    {{ $formatCurrency($item['price']) }}
                                                </p>
                                            </div>
                                            <span
                                                class="font-semibold text-on-primary">{{ $formatCurrency($item['total']) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 rounded-2xl bg-primary-700/60 p-4">
                            <p class="text-sm font-semibold text-on-primary">Aun no hay detalle disponible</p>
                            <p class="mt-2 text-xs text-primary-200">
                                El desglose de productos aparecera automaticamente en cuanto registres una compra.
                            </p>
                        </div>
                    @endif
                </section>

                <section class="rounded-3xl border border-primary-800 bg-primary-800 p-6 shadow-2xl shadow-primary-900/10">
                    <div class="flex items-center gap-3 text-warning">
                        <span class="material-symbols-outlined">insights</span>
                        <h4 class="text-sm font-bold text-on-primary">Lectura operativa</h4>
                    </div>
                    <p class="mt-3 text-sm leading-7 text-primary-200">
                        Esta vista resume compras por fecha, productos y monto acumulado. Los datos visibles dependen del
                        registro actual de productos vinculados a cada compra.
                    </p>

                    <div class="mt-5 grid gap-3">
                        <div class="rounded-2xl bg-surface-alt p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">Mes actual</p>
                            <p class="mt-2 text-xl font-bold text-on-surface">
                                {{ $formatCurrency($totals['month_spend']) }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface-alt p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">Promedio por compra
                            </p>
                            <p class="mt-2 text-xl font-bold text-on-surface">{{ $formatCurrency($totals['avg_ticket']) }}
                            </p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
