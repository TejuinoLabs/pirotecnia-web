@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    <div class="mt-16 space-y-8 px-4 py-6 sm:p-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(360px,0.95fr)]">
            <div class="rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                        Dashboard operativo
                    </span>
                    <span
                        class="inline-flex rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-accent">
                        {{ $rangeLabel }}
                    </span>
                </div>

                <h2 class="mt-5 text-3xl font-bold tracking-tight text-on-primary">Panel de control con rango filtrado</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                    Todas las métricas comerciales y operativas del dashboard están calculadas con eventos entre
                    <span class="font-semibold text-on-primary">{{ $rangeLabel }}</span>.
                    La comparación de tendencia usa el rango anterior equivalente:
                    <span class="font-semibold text-on-primary">{{ $comparisonRangeLabel }}</span>.
                </p>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Eventos filtrados</p>
                        <p class="mt-3 text-3xl font-bold text-on-primary">{{ $eventsInRangeCount }}</p>
                    </article>
                    <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Pendientes en rango</p>
                        <p class="mt-3 text-3xl font-bold text-secondary">{{ $futureEventsInRangeCount }}</p>
                    </article>
                    <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Comparativo</p>
                        <p class="mt-3 text-lg font-bold text-on-primary">{{ $comparisonRangeLabel }}</p>
                    </article>
                </div>
            </div>

            <div class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Rango de fechas</p>
                        <h3 class="mt-2 text-3xl font-bold text-on-primary">Filtrar dashboard</h3>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-secondary">date_range</span>
                </div>

                <form action="{{ route('dashboard') }}" class="mt-6 space-y-4" method="GET">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-primary-100">Desde</span>
                            <input
                                class="w-full rounded-2xl border border-primary-600/50 bg-on-primary/10 px-4 py-3 text-sm text-on-primary outline-none transition focus:border-secondary"
                                name="start_date" type="date" value="{{ $selectedStartDate }}">
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-primary-100">Hasta</span>
                            <input
                                class="w-full rounded-2xl border border-primary-600/50 bg-on-primary/10 px-4 py-3 text-sm text-on-primary outline-none transition focus:border-secondary"
                                name="end_date" type="date" value="{{ $selectedEndDate }}">
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            class="inline-flex items-center gap-2 rounded-2xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition hover:bg-secondary-600"
                            type="submit">
                            <span class="material-symbols-outlined text-base">filter_alt</span>
                            Aplicar rango
                        </button>
                        <a class="inline-flex items-center gap-2 rounded-2xl bg-on-primary/10 px-5 py-3 text-sm font-semibold text-on-primary transition hover:bg-on-primary/20"
                            href="{{ route('dashboard') }}">
                            <span class="material-symbols-outlined text-base">restart_alt</span>
                            Restablecer
                        </a>
                    </div>
                </form>

                <div class="mt-8 grid gap-3 sm:grid-cols-3">
                    <a href="{{ route('employees.create') }}"
                        class="flex items-center gap-2 rounded-2xl bg-on-primary/10 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="material-symbols-outlined text-secondary">add_circle</span>
                        Alta empleado
                    </a>
                    <a href="{{ route('sales.create') }}"
                        class="flex items-center gap-2 rounded-2xl bg-on-primary/10 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="material-symbols-outlined text-accent">receipt_long</span>
                        Registrar venta
                    </a>
                    <a href="{{ route('events.create') }}"
                        class="flex items-center gap-2 rounded-2xl bg-on-primary/10 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="material-symbols-outlined text-warning">rocket_launch</span>
                        Nuevo evento
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            <livewire:dashboard.kpi-card title="Ingreso contratado" icon="payments" backgroundColorIcon="bg-primary/10"
                colorIcon="text-primary" :value="$monthlyRevenue" indicatorIcon="trending_up"
                :indicatorLabel="$monthlyRevenueIndicator" :indicatorColor="$monthlyRevenueIndicatorColor" />
            <livewire:dashboard.kpi-card title="Eventos en rango" icon="event_available" backgroundColorIcon="bg-accent/10"
                colorIcon="text-accent" :value="$eventsInRangeCount" indicatorIcon="event_upcoming"
                :indicatorLabel="$eventsInRangeIndicator" indicatorColor="text-primary-200" />
            <livewire:dashboard.kpi-card title="Stock comprometido" icon="warning" backgroundColorIcon="bg-error/10"
                colorIcon="text-error" :value="$lowStockCount" indicatorIcon="priority_high"
                :indicatorLabel="$lowStockIndicator" :indicatorColor="$lowStockIndicatorColor" />
            <livewire:dashboard.kpi-card title="Paquetes contratados" icon="package_2"
                backgroundColorIcon="bg-secondary/10" colorIcon="text-secondary" :value="$packagesInRange"
                indicatorIcon="inventory_2" :indicatorLabel="$packagesInRangeIndicator"
                :indicatorColor="$packagesInRangeIndicatorColor" />
        </div>

        <div class="grid grid-cols-12 items-start gap-8">
            <div class="col-span-12 rounded-3xl bg-primary-800 p-8 lg:col-span-8">
                <div class="mb-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-on-primary">Rendimiento comercial</h3>
                        <p class="text-sm text-primary-200">
                            Ingreso contratado agrupado por {{ $chartGranularityLabel }} dentro de {{ $rangeLabel }}.
                        </p>
                    </div>
                    <div class="flex gap-2 rounded-lg bg-primary-700 p-1 text-xs">
                        <span class="rounded-md bg-primary-100 px-3 py-1 font-semibold text-primary-800">Ingresos</span>
                        <span class="px-3 py-1 font-semibold text-primary-200">{{ $comparisonRangeLabel }}</span>
                    </div>
                </div>

                <livewire:chart type="line" :series="$monthlyRevenueSeries" emptyMessage="Sin ingresos para el rango seleccionado." />
            </div>

            <div class="col-span-12 flex h-full flex-col rounded-3xl bg-primary-800 p-8 lg:col-span-4">
                <h3 class="text-xl font-bold text-on-primary">Eventos por categoría</h3>
                <p class="mb-8 text-sm text-primary-200">Distribución de eventos dentro del rango seleccionado.</p>
                <livewire:chart type="dona" :segments="$eventTypeSegments" centerLabel="Eventos"
                    emptyMessage="Sin categorías registradas en el rango." />
            </div>

            <div class="col-span-12 overflow-hidden rounded-3xl bg-primary-800 lg:col-span-8">
                <div class="flex flex-col gap-4 border-b border-primary-700/60 p-8 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-on-primary">Cobertura de empleados</h3>
                        <p class="mt-1 text-sm text-primary-200">
                            Eventos cubiertos por colaborador dentro de {{ $rangeLabel }}.
                        </p>
                    </div>
                    <a class="inline-flex items-center gap-2 text-xs font-semibold text-primary hover:underline"
                        href="{{ route('employees.index') }}">
                        <span class="material-symbols-outlined text-base">groups</span>
                        Ver plantilla
                    </a>
                </div>

                <div class="p-4 sm:p-6">
                    @if (count($employeeCoverageRows) > 0)
                        @php
                            $coverageLeaders = collect($employeeCoverageRows)->take(3)->values();
                        @endphp

                        <div class="mb-6 grid gap-4 xl:grid-cols-3">
                            @foreach ($coverageLeaders as $leaderIndex => $leader)
                                @php
                                    $position = $leaderIndex + 1;
                                    $toneClasses = match ($position) {
                                        1 => 'border-secondary/40 bg-secondary/10',
                                        2 => 'border-accent/40 bg-accent/10',
                                        default => 'border-warning/40 bg-warning/10',
                                    };
                                    $badgeClasses = match ($position) {
                                        1 => 'bg-secondary text-on-secondary',
                                        2 => 'bg-accent text-on-accent',
                                        default => 'bg-warning text-on-warning',
                                    };
                                @endphp

                                <article class="rounded-2xl border {{ $toneClasses }} p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                                {{ $position === 1 ? 'Lider de cobertura' : 'Top ' . $position }}
                                            </p>
                                            <p class="mt-3 text-lg font-bold text-on-primary">{{ $leader['name'] }}</p>
                                            <p class="mt-1 text-xs text-primary-200">{{ $leader['experienceLevel'] }}</p>
                                        </div>
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-black {{ $badgeClasses }}">
                                            #{{ $position }}
                                        </span>
                                    </div>

                                    <div class="mt-5 flex items-end justify-between gap-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.18em] text-primary-200">Eventos cubiertos</p>
                                            <p class="mt-2 text-3xl font-black text-on-primary">{{ $leader['eventCount'] }}</p>
                                        </div>
                                        <p class="text-right text-xs leading-5 text-primary-200">{{ $leader['referenceLabel'] }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="overflow-x-auto">
                            <table class="responsive-stack-table responsive-stack-table-dark min-w-[760px] w-full text-left">
                                <thead>
                                    <tr class="border-b border-primary-700/60 bg-primary-900/40 text-[11px] uppercase tracking-[0.22em] text-primary-200">
                                        <th class="px-4 py-4 font-bold">Empleado</th>
                                        <th class="px-4 py-4 font-bold text-center">Eventos</th>
                                        <th class="px-4 py-4 font-bold text-center">Pendientes</th>
                                        <th class="px-4 py-4 font-bold text-center">Finalizados</th>
                                        <th class="px-4 py-4 font-bold text-right">Referencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employeeCoverageRows as $employeeCoverage)
                                        <tr class="border-b border-primary-700/40 text-sm text-primary-100">
                                            <td class="px-4 py-5">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-secondary/15 text-secondary">
                                                        <span class="text-sm font-black uppercase">
                                                            {{ \Illuminate\Support\Str::of($employeeCoverage['name'])->trim()->substr(0, 2) }}
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="truncate font-semibold text-on-primary">{{ $employeeCoverage['name'] }}</p>
                                                        <p class="mt-1 text-xs text-primary-200">{{ $employeeCoverage['experienceLevel'] }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-5 text-center">
                                                <span class="font-bold text-on-primary">{{ $employeeCoverage['eventCount'] }}</span>
                                            </td>
                                            <td class="px-4 py-5 text-center">
                                                <span class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-xs font-bold text-secondary">
                                                    {{ $employeeCoverage['upcomingCount'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-5 text-center">
                                                <span class="inline-flex rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-bold text-accent">
                                                    {{ $employeeCoverage['completedCount'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-5 text-right text-xs text-primary-200">
                                                {{ $employeeCoverage['referenceLabel'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-primary-700/60 bg-primary-900/30 px-6 py-10 text-center">
                            <span class="material-symbols-outlined text-4xl text-primary-300">groups</span>
                            <p class="mt-4 text-lg font-bold text-on-primary">Sin cobertura registrada en el rango</p>
                            <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-primary-200">
                                Los eventos encontrados en este periodo todavía no tienen empleados asignados o no hubo
                                eventos dentro del filtro aplicado.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-span-12 h-full rounded-3xl bg-primary-800 p-8 lg:col-span-4">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-on-primary">Resumen operativo</h3>
                        <p class="mt-1 text-sm text-primary-200">Cobertura humana dentro del rango activo.</p>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-secondary">supervisor_account</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <article class="rounded-2xl border border-primary-700/60 bg-primary-900/40 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Colaboradores programados</p>
                        <p class="mt-3 text-3xl font-bold text-on-primary">{{ $coveredEmployeesCount }}</p>
                        <p class="mt-2 text-xs text-primary-200">Empleados con al menos un evento dentro del rango.</p>
                    </article>

                    <article class="rounded-2xl border border-primary-700/60 bg-primary-900/40 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Coberturas asignadas</p>
                        <p class="mt-3 text-3xl font-bold text-secondary">{{ $employeeCoverageAssignments }}</p>
                        <p class="mt-2 text-xs text-primary-200">Suma total de asignaciones empleado-evento.</p>
                    </article>

                    <article class="rounded-2xl border border-primary-700/60 bg-primary-900/40 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Promedio por empleado</p>
                        <p class="mt-3 text-3xl font-bold text-accent">{{ $averageCoveragePerEmployee }}</p>
                        <p class="mt-2 text-xs text-primary-200">Eventos cubiertos en promedio por colaborador.</p>
                    </article>
                </div>

                <div class="mt-4 rounded-2xl border border-primary-700/60 bg-primary-900/30 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Eventos sin equipo humano</p>
                    <p class="mt-3 text-lg font-bold {{ $eventsWithoutEmployeesCount > 0 ? 'text-warning' : 'text-secondary' }}">
                        {{ $eventsWithoutEmployeesCount }}
                    </p>
                    <p class="mt-2 text-sm text-primary-100">
                        {{ $eventsWithoutEmployeesCount > 0
                            ? 'Todavia requieren asignacion de personal dentro del rango.'
                            : 'Todos los eventos del rango ya tienen personal asignado.' }}
                    </p>
                </div>
            </div>

            <div class="col-span-12 overflow-hidden rounded-3xl bg-primary-800 lg:col-span-8">
                <div class="p-8 pb-4">
                    <h3 class="text-xl font-bold text-on-primary">Eventos pendientes del rango</h3>
                    <p class="mt-1 text-sm text-primary-200">Primeros 5 eventos pendientes dentro del filtro actual.</p>
                </div>
                <div class="overflow-x-auto px-2">
                    <livewire:dashboard.event-list :events="$upcomingEvents"
                        emptyMessage="No hay eventos pendientes dentro del rango seleccionado." />
                </div>
            </div>

            <div class="col-span-12 h-full rounded-3xl bg-primary-800 p-8 lg:col-span-4">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-on-primary">Bajo stock proyectado</h3>
                        <p class="mt-1 text-sm text-primary-200">Considera apartados activos dentro del rango.</p>
                    </div>
                    <a class="text-xs font-semibold text-primary hover:underline" href="{{ route('inventories.index') }}">Ver todo</a>
                </div>

                <div class="space-y-4">
                    @forelse ($lowStockItems as $item)
                        <livewire:info-summary-card :icon="$item['icon']" :title="$item['title']"
                            :subtitle="$item['subtitle']" :value="$item['value']" :subValue="$item['subValue']"
                            :color="$item['color']" :key="'low-stock-'.$lowStockItems->currentPage().'-'.$loop->index" />
                    @empty
                        <div class="rounded-2xl bg-primary-700/70 p-4">
                            <p class="text-sm font-semibold text-on-primary">Sin alertas para el rango</p>
                            <p class="mt-1 text-xs text-primary-200">
                                No hay productos comprometidos por debajo del mínimo en este periodo.
                            </p>
                        </div>
                    @endforelse
                </div>

                @if ($lowStockItems->total())
                    <x-panel.pagination :paginator="$lowStockItems" />
                @endif

                <div
                    class="relative mt-8 overflow-hidden rounded-2xl border border-primary-500/20 bg-gradient-to-br from-primary-700 to-primary-600 p-6">
                    <span
                        class="material-symbols-outlined absolute -bottom-4 -right-4 rotate-12 text-7xl text-on-primary/5">inventory</span>
                    <p class="mb-2 font-bold text-on-primary">Resumen del rango</p>
                    <p class="mb-4 text-xs text-primary-200">
                        @if ($lowStockCount > 0)
                            {{ $criticalLowStockCount }} críticos y {{ $lowStockCount - $criticalLowStockCount }} en seguimiento por debajo del mínimo.
                        @else
                            Inventario estable para el rango filtrado.
                        @endif
                    </p>
                    <a href="{{ route('purchases.create') }}"
                        class="rounded-lg bg-on-primary/10 px-4 py-2 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        Generar orden
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
