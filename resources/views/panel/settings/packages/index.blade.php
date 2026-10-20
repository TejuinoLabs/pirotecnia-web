@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Paquetes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Configuración</a></li>
                        <li class="breadcrumb-item active">Paquetes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $formatCurrency = fn($amount) => '$' . number_format((float) $amount, 2);
        $packageRows = $packages->values();
        $selectedPackage = $packageRows->sortByDesc('events_count')->first() ?? $packageRows->first();
        $configuredPackages = $packageRows
            ->filter(fn($package) => $package->materials->isNotEmpty() || $package->equipments->isNotEmpty())
            ->count();
        $mostReservedPackage = $packageRows->sortByDesc('events_count')->first();
        $highestValuePackage = $packageRows->sortByDesc(fn($package) => (float) $package->price)->first();

        $statusMeta = function ($package) {
            $hasComposition = $package->materials->isNotEmpty() || $package->equipments->isNotEmpty();

            if (!$hasComposition) {
                return [
                    'label' => 'Incompleto',
                    'classes' => 'border-warning/30 bg-warning/10 text-warning',
                ];
            }

            if ((int) $package->events_count > 0) {
                return [
                    'label' => 'Reservado',
                    'classes' => 'border-secondary/30 bg-secondary/10 text-secondary',
                ];
            }

            return [
                'label' => 'Disponible',
                'classes' => 'border-accent/30 bg-accent/10 text-accent',
            ];
        };
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
                            Catalogo de Paquetes
                        </span>
                        <span
                            class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-secondary">
                            {{ number_format($packageRows->count()) }} registros configurados
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-secondary">Comercial y Operativo
                        </p>
                        <h2 class="mt-4 font-heading text-4xl font-bold tracking-tight text-on-primary xl:text-5xl">
                            Paquetes listos para venta, edición y seguimiento interno.
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                            Administra paquetes comerciales, revisa su composición, valida nivel operativo y mantén control
                            del catálogo desde una vista alineada al branding del panel.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-secondary">inventory_2</span>
                                {{ number_format($configuredPackages) }} con composición cargada
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-accent">sell</span>
                                {{ $mostReservedPackage ? $mostReservedPackage->name : 'Sin reservas aún' }}
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-warning">payments</span>
                                {{ $highestValuePackage ? $formatCurrency($highestValuePackage->price) : '$0.00' }} valor
                                tope
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Total de
                                paquetes</p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">{{ number_format($packageRows->count()) }}
                            </p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Con reservas
                            </p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">
                                {{ number_format($packageRows->filter(fn($package) => (int) $package->events_count > 0)->count()) }}
                            </p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Mayor valor
                            </p>
                            <p class="mt-3 text-3xl font-bold text-secondary">
                                {{ $highestValuePackage ? $formatCurrency($highestValuePackage->price) : '$0.00' }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Centro de Acciones</p>
                <h3 class="mt-3 text-3xl font-bold text-on-primary">Gestión del catálogo</h3>
                <p class="mt-2 text-sm text-primary-100">
                    Accesos directos para crear paquetes nuevos, revisar configuraciones y administrar materiales.
                </p>

                <div class="mt-8 grid gap-3">
                    <a href="{{ route('packages.create') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-secondary px-5 py-4 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-[0.99]">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined">add_circle</span>
                            Nuevo paquete
                        </span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>

                    <a href="{{ route('settings.products.index') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-accent">inventory</span>
                            Catálogo de productos
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>

                    <a href="{{ route('settings.equipments.index') }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-warning">construction</span>
                            Equipamiento
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>
                </div>

                <div class="mt-8 rounded-2xl bg-on-primary/10 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Lectura rápida</p>

                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl bg-primary-800/40 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-on-primary">Paquete más reservado</span>
                                <span
                                    class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-secondary">
                                    {{ $mostReservedPackage ? $mostReservedPackage->events_count : 0 }} eventos
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-primary-200">
                                {{ $mostReservedPackage ? $mostReservedPackage->name : 'Sin historial de reservas todavía.' }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-primary-800/40 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm font-semibold text-on-primary">Valor máximo</span>
                                <span
                                    class="inline-flex rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-warning">
                                    {{ $highestValuePackage ? $formatCurrency($highestValuePackage->price) : '$0.00' }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-primary-200">
                                {{ $highestValuePackage ? $highestValuePackage->name : 'Sin paquetes capturados.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Total de paquetes</p>
                <p class="mt-4 text-4xl font-bold text-on-primary">{{ number_format($packageRows->count()) }}</p>
                <p class="mt-4 text-sm text-primary-200">Catálogo comercial disponible en configuración.</p>
            </article>
            <article class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Paquetes configurados</p>
                <p class="mt-4 text-4xl font-bold text-on-primary">{{ number_format($configuredPackages) }}</p>
                <p class="mt-4 text-sm text-primary-200">Con materiales o equipos ya vinculados al paquete.</p>
            </article>
            <article class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Más reservado</p>
                <p class="mt-4 text-2xl font-bold text-on-primary">
                    {{ $mostReservedPackage ? $mostReservedPackage->name : 'Sin dato' }}
                </p>
                <p class="mt-4 text-sm text-primary-200">
                    {{ $mostReservedPackage ? $mostReservedPackage->events_count . ' eventos asociados' : 'Aún sin reservas registradas.' }}
                </p>
            </article>
            <article class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Mayor valor</p>
                <p class="mt-4 text-4xl font-bold text-secondary">
                    {{ $highestValuePackage ? $formatCurrency($highestValuePackage->price) : '$0.00' }}
                </p>
                <p class="mt-4 text-sm text-primary-200">
                    {{ $highestValuePackage ? $highestValuePackage->name : 'No hay paquetes disponibles.' }}
                </p>
            </article>
        </div>

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="min-w-0 space-y-6">
                <div class="rounded-3xl bg-primary-800 p-5 shadow-2xl shadow-primary-900/10">
                    <div class="grid gap-4 lg:grid-cols-1">
                        <div class="relative min-w-0">
                            <span
                                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted">search</span>
                            <input
                                class="w-full rounded-xl border border-border bg-surface-alt py-3 pl-10 pr-4 text-sm text-on-surface placeholder:text-muted focus:ring-1 focus:ring-accent/30"
                                placeholder="Buscar por nombre o código..." type="text" />
                        </div>


                    </div>
                </div>

                <section class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/10">
                    <div
                        class="flex flex-col gap-4 border-b border-border px-6 py-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-secondary">Listado de paquetes
                            </p>
                            <h3 class="mt-3 text-2xl font-bold text-on-primary">Configuraciones registradas</h3>
                            <p class="mt-2 text-sm text-primary-200">
                                Busca paquetes por nombre, código, descripción o nivel operativo.
                            </p>
                        </div>
                        <div
                            class="inline-flex items-center gap-2 rounded-full bg-surface-alt px-4 py-2 text-sm text-muted">
                            <span class="material-symbols-outlined text-accent">table_view</span>
                            {{ number_format($packageRows->count()) }} registros en total
                        </div>
                    </div>

                    <form method="GET" action="{{ route('settings.packages.index') }}" class="flex flex-wrap items-end gap-3 border-b border-border px-6 py-4">
                        <div class="min-w-0 flex-1">
                            <label for="package-search" class="mb-2 block text-sm font-semibold text-primary-200">Buscar paquetes</label>
                            <input id="package-search" type="search" name="search" value="{{ $search }}"
                                placeholder="Nombre, código, descripción o nivel"
                                class="w-full rounded-xl border border-primary-600 bg-primary-700 px-4 py-3 text-on-primary placeholder:text-primary-200">
                        </div>
                        <button type="submit" class="rounded-xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary">Buscar</button>
                        @if ($search !== '')
                            <a href="{{ route('settings.packages.index') }}" class="rounded-xl border border-primary-600 px-5 py-3 text-sm text-primary-200">Limpiar</a>
                        @endif
                    </form>

                    @if ($listRows->isEmpty())
                        <div class="p-10">
                            <div class="rounded-3xl border border-border bg-surface-alt p-8 text-center">
                                <span class="material-symbols-outlined text-5xl text-primary-300">inventory_2</span>
                                <p class="mt-4 text-lg font-bold text-on-surface">{{ $search !== '' ? 'No se encontraron paquetes' : 'Sin paquetes registrados' }}</p>
                                <p class="mt-2 text-sm text-muted">
                                    {{ $search !== '' ? 'Prueba con otro término o limpia la búsqueda.' : 'Crea el primer paquete para comenzar a gestionar el catálogo comercial.' }}
                                </p>
                                <a href="{{ route('packages.create') }}"
                                    class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    Nuevo paquete
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="responsive-stack-table responsive-stack-table-dark min-w-[1080px] w-full text-left">
                                <thead class="bg-primary-500">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary">
                                            Nombre del paquete</th>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary">
                                            Tipo / Nivel</th>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary">
                                            Contenido</th>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary">
                                            Reservas</th>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary">
                                            Precio</th>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary">
                                            Estatus</th>
                                        <th
                                            class="px-6 py-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-on-primary text-center">
                                            Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($listRows as $package)
                                        @php
                                            $status = $statusMeta($package);
                                            $isFeatured = $selectedPackage && $selectedPackage->id === $package->id;
                                        @endphp
                                        <tr
                                            class="transition-colors hover:bg-primary-900 {{ $isFeatured ? 'bg-accent/5 border-l-4 border-accent' : '' }}">
                                            <td class="px-6 py-5" data-label="Paquete">
                                                <p class="font-semibold text-on-primary">{{ $package->name }}</p>
                                                <p class="mt-1 text-xs text-primary-200">COD:
                                                    PKG-{{ str_pad((string) $package->id, 4, '0', STR_PAD_LEFT) }}</p>
                                            </td>
                                            <td class="px-6 py-5" data-label="Tipo / nivel">
                                                <div class="flex flex-col gap-2">
                                                    <span class="text-sm text-on-primary">
                                                        {{ optional($package->experienceLevel)->name ?: 'Sin nivel asignado' }}
                                                    </span>
                                                    <span
                                                        class="inline-flex w-fit rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-semibold text-accent">
                                                        {{ filled($package->duration) ? $package->duration : 'Duración no capturada' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5" data-label="Contenido">
                                                <div class="flex gap-4">
                                                    <div class="text-center">
                                                        <p class="text-base font-bold text-on-primary">
                                                            {{ $package->materials->count() }}</p>
                                                        <p
                                                            class="text-[10px] uppercase tracking-[0.18em] text-primary-200">
                                                            Materiales</p>
                                                    </div>
                                                    <div class="h-8 w-px bg-border self-center"></div>
                                                    <div class="text-center">
                                                        <p class="text-base font-bold text-on-primary">
                                                            {{ $package->equipments->count() }}</p>
                                                        <p
                                                            class="text-[10px] uppercase tracking-[0.18em] text-primary-200">
                                                            Equipos</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5" data-label="Reservas">
                                                <span
                                                    class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-xs font-semibold text-secondary">
                                                    {{ number_format($package->events_count) }} eventos
                                                </span>
                                            </td>
                                            <td class="px-6 py-5 text-sm font-bold text-on-primary" data-label="Precio">
                                                {{ $formatCurrency($package->price) }}
                                            </td>
                                            <td class="px-6 py-5" data-label="Estatus">
                                                <span
                                                    class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                                    {{ $status['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-5" data-label="Acciones">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="{{ route('packages.show', $package->id) }}"
                                                        class="inline-flex rounded-lg p-2 text-primary-200 transition-colors hover:bg-surface-alt hover:text-accent">
                                                        <span
                                                            class="material-symbols-outlined text-[20px]">visibility</span>
                                                    </a>
                                                    <a href="{{ route('packages.edit', $package->id) }}"
                                                        class="inline-flex rounded-lg p-2 text-primary-200 transition-colors hover:bg-surface-alt hover:text-secondary">
                                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                                    </a>
                                                    <button
                                                        class="inline-flex rounded-lg p-2 text-primary-200 transition-colors hover:bg-surface-alt hover:text-warning"
                                                        type="button">
                                                        <span
                                                            class="material-symbols-outlined text-[20px]">content_copy</span>
                                                    </button>
                                                    <button
                                                        class="inline-flex rounded-lg p-2 text-error transition-colors hover:bg-error/10"
                                                        onclick="confirmDelete({{ $package->id }})" type="button">
                                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                                    </button>
                                                    <form action="{{ route('packages.destroy', $package->id) }}"
                                                        class="hidden" id="deleteForm-{{ $package->id }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @endif
                    <x-panel.pagination :paginator="$listRows" />
                </section>
            </div>

            <aside class="self-start xl:sticky xl:top-24">
                <div
                    class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/20 xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto">
                    @if ($selectedPackage)
                        <div
                            class="relative overflow-hidden border-b border-primary-700/60 bg-gradient-to-br from-primary to-primary-700 px-6 py-6">
                            <div class="absolute -right-10 -top-12 h-36 w-36 rounded-full bg-secondary/15 blur-3xl"></div>
                            <div class="absolute bottom-0 left-0 h-24 w-24 rounded-full bg-accent/15 blur-3xl"></div>
                            <div class="relative">
                                <span
                                    class="inline-flex rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-warning">
                                    {{ $selectedPackage->events_count > 0 ? 'Más reservado' : 'Paquete destacado' }}
                                </span>
                                <h3 class="mt-4 text-2xl font-bold text-on-primary">{{ $selectedPackage->name }}</h3>
                                <p class="mt-2 text-sm text-primary-100">
                                    {{ $selectedPackage->description ?: 'Este paquete aún no tiene descripción capturada.' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-6 p-6">
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div class="rounded-2xl bg-primary-700/60 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                        Precio sugerido</p>
                                    <p class="mt-3 text-3xl font-bold text-secondary">
                                        {{ $formatCurrency($selectedPackage->price) }}</p>
                                </div>
                                <div class="rounded-2xl bg-primary-700/60 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">
                                        Duración</p>
                                    <p class="mt-3 text-xl font-bold text-on-primary">
                                        {{ filled($selectedPackage->duration) ? $selectedPackage->duration : 'Sin capturar' }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-[0.22em] text-primary-200">Materiales
                                        incluidos</h4>
                                    <span class="text-[11px] text-primary-200">{{ $selectedPackage->materials->count() }}
                                        items</span>
                                </div>
                                <div class="space-y-2">
                                    @forelse ($selectedPackage->materials->take(6) as $material)
                                        <div class="flex items-center justify-between rounded-2xl bg-primary-700/60 p-3">
                                            <div class="flex items-center gap-3">
                                                <span
                                                    class="h-2 w-2 rounded-full {{ $loop->index % 3 === 0 ? 'bg-secondary' : ($loop->index % 3 === 1 ? 'bg-accent' : 'bg-warning') }}"></span>
                                                <span class="text-sm text-on-primary">{{ $material->name }}</span>
                                            </div>
                                            <span
                                                class="text-sm font-semibold text-on-primary">x{{ (float) ($material->pivot->quantity ?? 0) }}</span>
                                        </div>
                                    @empty
                                        <div class="rounded-2xl bg-primary-700/60 p-4">
                                            <p class="text-sm font-semibold text-on-primary">Sin materiales registrados</p>
                                            <p class="mt-1 text-xs text-primary-200">Agrega productos al paquete para
                                                completar su configuración.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-[0.22em] text-primary-200">Equipamiento
                                    </h4>
                                    <span class="text-[11px] text-primary-200">{{ $selectedPackage->equipments->count() }}
                                        requeridos</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($selectedPackage->equipments->take(8) as $equipment)
                                        <span
                                            class="inline-flex items-center gap-2 rounded-xl bg-primary-700/60 px-3 py-2 text-xs text-on-primary">
                                            <span
                                                class="material-symbols-outlined text-[14px] text-accent">construction</span>
                                            {{ $equipment->name }}
                                        </span>
                                    @empty
                                        <span
                                            class="inline-flex items-center gap-2 rounded-xl bg-primary-700/60 px-3 py-2 text-xs text-primary-200">
                                            Sin equipamiento configurado
                                        </span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-2xl border border-warning/20 bg-warning/10 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-warning">auto_awesome</span>
                                    <div>
                                        <h4 class="text-sm font-bold text-on-primary">Lectura operativa</h4>
                                        <p class="mt-1 text-xs leading-6 text-primary-100">
                                            {{ $selectedPackage->events_count > 0
                                                ? 'Este paquete ya tiene reservas asociadas. Conviene revisar stock y disponibilidad de equipos antes de duplicarlo.'
                                                : 'Este paquete aún no registra reservas. Puedes terminar su configuración y dejarlo listo para venta.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-3 border-t border-primary-700/60 pt-6">
                                <a href="{{ route('packages.edit', $selectedPackage->id) }}"
                                    class="inline-flex flex-1 items-center justify-center rounded-2xl bg-secondary px-4 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600">
                                    Editar configuración
                                </a>
                                <a href="{{ route('packages.show', $selectedPackage->id) }}"
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-700 text-on-primary transition-colors hover:bg-primary-600">
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="p-6">
                            <div class="rounded-2xl bg-primary-700/60 p-5">
                                <p class="text-lg font-bold text-on-primary">Sin paquete destacado</p>
                                <p class="mt-2 text-sm text-primary-200">
                                    El panel lateral mostrará detalle, materiales y equipamiento del catálogo en cuanto
                                    exista al menos un paquete.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
@endsection

@section('extra-script')
    <script>
        function editPackage() {
            localStorage.removeItem('activeTab');
        }

        function confirmDelete(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#1F8ABF',
                cancelButtonColor: '#BA1A1A',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm-' + id).submit();
                }
            })
        }
    </script>
@endsection
