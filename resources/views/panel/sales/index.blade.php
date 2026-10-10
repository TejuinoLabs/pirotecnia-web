@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Ventas</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item active">Ventas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $saleRows = $sales
            ->map(function ($sale) {
                $clientName = trim((string) $sale->client_name);
                $clientEmail = trim((string) $sale->client_email);
                $clientPhone = trim((string) $sale->client_phone);
                $clientAddress = trim((string) ($sale->client_address ?? ''));
                $clientCity = trim((string) ($sale->client_city ?? ''));
                $clientState = trim((string) ($sale->client_state ?? ''));
                $clientCountry = trim((string) ($sale->client_country ?? ''));
                $location = collect([$clientCity, $clientState, $clientCountry])
                    ->filter()
                    ->implode(', ');
                $hasFullProfile =
                    $clientEmail !== '' && $clientPhone !== '' && ($clientAddress !== '' || $location !== '');
                $hasContact = $clientEmail !== '' || $clientPhone !== '';

                return [
                    'id' => $sale->id,
                    'folio' => 'VTA-' . str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT),
                    'client_name' => $clientName !== '' ? $clientName : 'Cliente sin nombre',
                    'client_email' => $clientEmail,
                    'client_phone' => $clientPhone,
                    'client_address' => $clientAddress,
                    'location' => $location !== '' ? $location : 'Ubicacion sin capturar',
                    'client_type_id' => $sale->client_type_id,
                    'created_at' => $sale->created_at,
                    'status' => $hasFullProfile ? 'Completa' : ($hasContact ? 'Seguimiento' : 'Basica'),
                    'status_tone' => $hasFullProfile ? 'secondary' : ($hasContact ? 'warning' : 'accent'),
                ];
            })
            ->values();

        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $salesThisMonth = $saleRows->filter(function ($sale) use ($currentMonthStart, $currentMonthEnd) {
            return $sale['created_at']?->between($currentMonthStart, $currentMonthEnd) ?? false;
        });
        $salesThisWeek = $saleRows->filter(function ($sale) {
            return $sale['created_at']?->isCurrentWeek() ?? false;
        });

        $totalSales = $saleRows->count();
        $uniqueClients = $saleRows->pluck('client_name')->filter()->unique()->count();
        $salesWithEmail = $saleRows->filter(fn($sale) => $sale['client_email'] !== '')->count();
        $salesWithAddress = $saleRows
            ->filter(fn($sale) => $sale['client_address'] !== '' || $sale['location'] !== 'Ubicacion sin capturar')
            ->count();
        $latestSale = $saleRows->first();

        $latestLocations = $saleRows
            ->pluck('location')
            ->filter(fn($location) => $location !== 'Ubicacion sin capturar')
            ->countBy()
            ->sortDesc()
            ->take(4);

        $kpis = [
            [
                'label' => 'Ventas registradas',
                'value' => number_format($totalSales),
                'helper' => number_format($salesThisMonth->count()) . ' altas durante el mes actual',
                'icon' => 'receipt_long',
                'tone' => 'secondary',
            ],
            [
                'label' => 'Clientes unicos',
                'value' => number_format($uniqueClients),
                'helper' => 'Base comercial capturada en el modulo',
                'icon' => 'groups',
                'tone' => 'accent',
            ],
            [
                'label' => 'Con correo',
                'value' => number_format($salesWithEmail),
                'helper' => 'Registros listos para seguimiento digital',
                'icon' => 'mail',
                'tone' => 'warning',
            ],
            [
                'label' => 'Con direccion',
                'value' => number_format($salesWithAddress),
                'helper' => 'Ubicaciones disponibles para referencia operativa',
                'icon' => 'location_on',
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
                <div class="absolute -left-12 top-10 h-44 w-44 rounded-full bg-secondary/15 blur-3xl"></div>
                <div class="absolute -right-10 bottom-0 h-52 w-52 rounded-full bg-accent/15 blur-3xl"></div>

                <div class="relative flex h-full flex-col justify-between gap-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            Centro Comercial
                        </span>
                        <span
                            class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-secondary">
                            {{ number_format($salesThisWeek->count()) }} registros esta semana
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-secondary">Seguimiento de Ventas
                        </p>
                        <h2 class="mt-4 font-heading text-4xl font-bold tracking-tight text-on-primary xl:text-5xl">
                            Clientes, altas y trazabilidad comercial desde una sola vista.
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                            La pantalla concentra el historial de ventas registradas, el estado del perfil de cada cliente y
                            la informacion base para seguimiento operativo y administrativo.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-secondary">person_add</span>
                                {{ number_format($uniqueClients) }} clientes diferentes capturados
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-accent">calendar_month</span>
                                {{ number_format($salesThisMonth->count()) }} altas durante el mes
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-warning">contact_mail</span>
                                {{ number_format($salesWithEmail) }} con correo de contacto
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Registros
                                totales</p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">{{ number_format($totalSales) }}</p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Con contacto
                                util</p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">
                                {{ number_format($saleRows->filter(fn($sale) => $sale['client_email'] !== '' || $sale['client_phone'] !== '')->count()) }}
                            </p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Ultima alta
                            </p>
                            <p class="mt-3 text-3xl font-bold text-secondary">
                                {{ $latestSale && $latestSale['created_at'] ? $latestSale['created_at']->format('d M') : '--' }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Centro de Acciones</p>
                <h3 class="mt-3 text-3xl font-bold text-on-primary">Gestion comercial</h3>
                <p class="mt-2 text-sm text-primary-100">
                    Accesos directos para alta de ventas, consulta del catalogo y regreso al tablero principal.
                </p>

                <div class="mt-8 grid gap-3">
                    <a href="{{ route('sales.create') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-secondary px-5 py-4 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-[0.99]">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined">add_business</span>
                            Registrar venta
                        </span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>

                    <a href="{{ route('settings.products.index') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-accent">inventory</span>
                            Catalogo de productos
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>

                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-warning">dashboard</span>
                            Volver al dashboard
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>
                </div>

                <div class="mt-8 rounded-2xl bg-on-primary/10 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Ubicaciones frecuentes
                    </p>

                    <div class="mt-4 space-y-3">
                        @forelse ($latestLocations as $location => $count)
                            <div class="rounded-2xl bg-primary-800/40 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-on-primary">{{ $location }}</span>
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] {{ $toneClasses[$loop->first ? 'secondary' : ($loop->index === 1 ? 'accent' : 'warning')] }}">
                                        {{ $count }} ventas
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-primary-800/40 p-4">
                                <p class="text-sm font-semibold text-on-primary">Sin ubicaciones registradas</p>
                                <p class="mt-1 text-xs text-primary-200">Las ciudades y estados apareceran conforme captures
                                    ventas.</p>
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
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-secondary">Historial Comercial</p>
                        <h3 class="mt-3 text-2xl font-bold text-on-primary">Ventas registradas</h3>
                        <p class="mt-2 text-sm text-primary-200">
                            Consulta clientes, datos de contacto, ubicacion y fecha de captura de cada venta.
                        </p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-surface-alt px-4 py-2 text-sm text-muted">
                        <span class="material-symbols-outlined text-accent">table_view</span>
                        {{ number_format($totalSales) }} filas visibles
                    </div>
                </div>

                @if ($saleRows->isEmpty())
                    <div class="p-10">
                        <div class="rounded-3xl border border-border bg-surface-alt p-8 text-center">
                            <span class="material-symbols-outlined text-5xl text-primary-300">receipt_long</span>
                            <p class="mt-4 text-lg font-bold text-on-surface">Sin ventas registradas</p>
                            <p class="mt-2 text-sm text-muted">
                                Registra la primera venta para comenzar a visualizar clientes, contactos y seguimiento
                                comercial.
                            </p>
                            <a href="{{ route('sales.create') }}"
                                class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600">
                                <span class="material-symbols-outlined">add_business</span>
                                Registrar venta
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
                                        Cliente</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Contacto</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Ubicacion</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Registro</th>
                                    <th
                                        class="px-6 py-4 text-center text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">
                                        Perfil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @php
                                    $listRows = \App\Support\PanelPagination::collection($saleRows);
                                @endphp
                                @foreach ($listRows as $sale)
                                    <tr class="transition-colors hover:bg-surface-alt">
                                        <td class="px-6 py-5" data-label="Folio">
                                            <p class="font-mono text-sm font-semibold text-primary">{{ $sale['folio'] }}
                                            </p>
                                        </td>
                                        <td class="px-6 py-5" data-label="Cliente">
                                            <p class="text-sm font-semibold text-on-surface">{{ $sale['client_name'] }}
                                            </p>
                                            <p class="mt-1 text-xs text-muted">Cliente #{{ $sale['id'] }}</p>
                                        </td>
                                        <td class="px-6 py-5" data-label="Contacto">
                                            <div class="space-y-1 text-sm">
                                                <p class="text-on-surface">
                                                    {{ $sale['client_email'] !== '' ? $sale['client_email'] : 'Sin correo' }}
                                                </p>
                                                <p class="text-muted">
                                                    {{ $sale['client_phone'] !== '' ? $sale['client_phone'] : 'Sin telefono' }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5" data-label="Ubicación">
                                            <p class="max-w-xs text-sm text-on-surface">{{ $sale['location'] }}</p>
                                            @if ($sale['client_address'] !== '')
                                                <p class="mt-1 text-xs text-muted">{{ $sale['client_address'] }}</p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-5 text-sm text-muted" data-label="Registro">
                                            {{ $sale['created_at'] ? $sale['created_at']->format('d/m/Y H:i') : 'Sin fecha' }}
                                        </td>
                                        <td class="px-6 py-5 text-center" data-label="Perfil">
                                            <span
                                                class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $toneClasses[$sale['status_tone']] }}">
                                                {{ $sale['status'] }}
                                            </span>
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
                                {{ $latestSale ? $latestSale['client_name'] : 'Sin ventas' }}
                            </h3>
                            <p class="mt-2 text-sm text-primary-200">
                                {{ $latestSale ? 'Ultimo registro capturado por el equipo comercial.' : 'No hay registros para mostrar.' }}
                            </p>
                        </div>
                        <span
                            class="inline-flex rounded-full border border-primary-600/50 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            {{ $latestSale ? $latestSale['folio'] : '--' }}
                        </span>
                    </div>

                    @if ($latestSale)
                        <div class="mt-6 space-y-4">
                            <div class="rounded-2xl bg-primary-700/60 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-primary-200">Correo</span>
                                    <span class="text-sm font-semibold text-on-primary">
                                        {{ $latestSale['client_email'] !== '' ? $latestSale['client_email'] : 'Sin correo' }}
                                    </span>
                                </div>
                                <div class="mt-3 spark-divider"></div>
                                <div class="mt-3 flex items-center justify-between gap-4">
                                    <span class="text-sm text-primary-200">Telefono</span>
                                    <span class="text-sm font-semibold text-secondary">
                                        {{ $latestSale['client_phone'] !== '' ? $latestSale['client_phone'] : 'Sin telefono' }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-primary-700/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Ubicacion
                                    del cliente</p>
                                <p class="mt-3 text-sm font-semibold text-on-primary">{{ $latestSale['location'] }}</p>
                                <p class="mt-2 text-xs leading-6 text-primary-200">
                                    {{ $latestSale['client_address'] !== '' ? $latestSale['client_address'] : 'Sin direccion capturada para este registro.' }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-primary-700/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Estado
                                    del perfil</p>
                                <div class="mt-3">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $toneClasses[$latestSale['status_tone']] }}">
                                        {{ $latestSale['status'] }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs leading-6 text-primary-200">
                                    Registro generado el
                                    {{ $latestSale['created_at'] ? $latestSale['created_at']->format('d/m/Y \a \l\a\s H:i') : 'sin fecha disponible' }}.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 rounded-2xl bg-primary-700/60 p-4">
                            <p class="text-sm font-semibold text-on-primary">Aun no hay detalle disponible</p>
                            <p class="mt-2 text-xs text-primary-200">
                                El resumen del ultimo registro aparecera automaticamente en cuanto captures una venta.
                            </p>
                        </div>
                    @endif
                </section>

                <section class="rounded-3xl border border-primary-800 bg-primary-800 p-6 shadow-2xl shadow-primary-900/10">
                    <div class="flex items-center gap-3 text-warning">
                        <span class="material-symbols-outlined">insights</span>
                        <h4 class="text-sm font-bold text-on-primary">Lectura comercial</h4>
                    </div>
                    <p class="mt-3 text-sm leading-7 text-primary-200">
                        Esta vista queda alineada al branding del panel y hoy resume altas, contactos y cobertura de datos
                        del cliente. No muestra montos porque el modulo actual de ventas no persiste totales confiables.
                    </p>

                    <div class="mt-5 grid gap-3">
                        <div class="rounded-2xl bg-surface-alt p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">Mes actual</p>
                            <p class="mt-2 text-xl font-bold text-on-surface">
                                {{ number_format($salesThisMonth->count()) }} ventas</p>
                        </div>
                        <div class="rounded-2xl bg-surface-alt p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted">Clientes unicos</p>
                            <p class="mt-2 text-xl font-bold text-on-surface">{{ number_format($uniqueClients) }}</p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
    <x-panel.pagination :paginator="$listRows ?? \App\Support\PanelPagination::collection($saleRows)" />
@endsection
