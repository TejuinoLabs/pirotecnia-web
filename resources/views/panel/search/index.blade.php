@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Buscador global</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item active">Buscar</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $toneClasses = [
            'secondary' => 'border-secondary/30 bg-secondary/10 text-secondary',
            'accent' => 'border-accent/30 bg-accent/10 text-accent',
            'warning' => 'border-warning/30 bg-warning/10 text-warning',
        ];
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(360px,0.88fr)]">
            <div class="relative overflow-hidden rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div class="absolute -left-10 top-12 h-40 w-40 rounded-full bg-accent/10 blur-3xl"></div>
                <div class="absolute -right-12 -top-10 h-44 w-44 rounded-full bg-secondary/10 blur-3xl"></div>

                <div class="relative">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            Busqueda transversal
                        </span>
                        @if ($query !== '')
                            <span
                                class="inline-flex rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-accent">
                                "{{ $query }}"
                            </span>
                        @endif
                    </div>

                    <h2 class="mt-5 text-4xl font-bold tracking-tight text-on-primary">Buscador global del panel</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                        Busca eventos, empleados, usuarios, productos, paquetes y ventas desde cualquier pantalla del
                        sistema con un solo flujo.
                    </p>

                    <form action="{{ route('search.global') }}" class="mt-8 flex flex-col gap-4 sm:flex-row" method="GET">
                        <label class="block flex-1">
                            <span class="sr-only">Buscar en el panel</span>
                            <div
                                class="flex items-center gap-3 rounded-2xl border border-primary-600/50 bg-primary-700/60 px-5 py-4 focus-within:border-secondary">
                                <span class="material-symbols-outlined text-primary-200">search</span>
                                <input
                                    class="w-full bg-transparent text-sm text-on-primary outline-none placeholder:text-primary-200"
                                    name="q" placeholder="Cliente, producto, telefono, folio o direccion"
                                    type="text" value="{{ $query }}">
                            </div>
                        </label>

                        <div class="flex flex-wrap gap-3">
                            <button
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-secondary px-5 py-4 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition hover:bg-secondary-600"
                                type="submit">
                                <span class="material-symbols-outlined text-base">travel_explore</span>
                                Buscar
                            </button>
                            @if ($query !== '')
                                <a class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-700 px-5 py-4 text-sm font-semibold text-on-primary transition hover:bg-primary-600"
                                    href="{{ route('search.global') }}">
                                    <span class="material-symbols-outlined text-base">restart_alt</span>
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <aside class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Estado de la busqueda</p>
                <h3 class="mt-3 text-3xl font-bold text-on-primary">
                    @if ($query === '')
                        Esperando termino
                    @elseif ($totalResults > 0)
                        {{ $totalResults }} resultados
                    @else
                        Sin coincidencias
                    @endif
                </h3>
                <p class="mt-2 text-sm text-primary-100">
                    @if ($query === '')
                        Escribe al menos 2 caracteres para buscar por nombre, cliente, producto o direccion.
                    @elseif ($totalResults > 0)
                        Se agruparon coincidencias por modulo para que puedas saltar directo al detalle.
                    @else
                        Prueba con otro nombre, telefono, folio o una palabra mas corta.
                    @endif
                </p>

                <div class="mt-8 space-y-3">
                    @foreach ($resultGroups as $group)
                        <div class="rounded-2xl bg-on-primary/10 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-secondary">{{ $group['icon'] }}</span>
                                    <div>
                                        <p class="text-sm font-semibold text-on-primary">{{ $group['label'] }}</p>
                                        <p class="text-xs text-primary-200">{{ $group['description'] }}</p>
                                    </div>
                                </div>
                                <span
                                    class="inline-flex rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] {{ $toneClasses[$group['tone']] }}">
                                    {{ $group['items'] instanceof \Illuminate\Pagination\LengthAwarePaginator ? $group['items']->total() : $group['items']->count() }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </aside>
        </section>

        @if ($searchNotice)
            <section class="rounded-3xl border border-primary-600/60 bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                <div class="flex items-start gap-4">
                    <span class="material-symbols-outlined text-secondary">info</span>
                    <div>
                        <p class="text-sm font-semibold text-on-primary">Como funciona la busqueda</p>
                        <p class="mt-2 text-sm text-primary-200">{{ $searchNotice }}</p>
                    </div>
                </div>
            </section>
        @endif

        @if ($canSearch && $activeGroups->isEmpty())
            <section class="rounded-3xl bg-primary-800 p-10 text-center shadow-2xl shadow-primary-900/20">
                <span class="material-symbols-outlined text-5xl text-primary-200">search_off</span>
                <h3 class="mt-4 text-2xl font-bold text-on-primary">No encontramos coincidencias</h3>
                <p class="mt-3 text-sm text-primary-200">
                    Ajusta el termino de busqueda o usa una referencia mas general para explorar el panel.
                </p>
            </section>
        @endif

        @if ($activeGroups->isNotEmpty())
            <section class="grid gap-6 xl:grid-cols-2">
                @foreach ($activeGroups as $group)
                    <article class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/20">
                        <div class="flex items-center justify-between gap-4 border-b border-primary-700/60 px-8 py-6">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Modulo</p>
                                <h3 class="mt-2 text-2xl font-bold text-on-primary">{{ $group['label'] }}</h3>
                                <p class="mt-1 text-sm text-primary-200">{{ $group['description'] }}</p>
                            </div>
                            <span
                                class="inline-flex rounded-full border px-3 py-2 text-xs font-bold uppercase tracking-[0.18em] {{ $toneClasses[$group['tone']] }}">
                                {{ $group['items'] instanceof \Illuminate\Pagination\LengthAwarePaginator ? $group['items']->total() : $group['items']->count() }} encontrados
                            </span>
                        </div>

                        <div class="space-y-3 p-6">
                            @foreach ($group['items'] as $item)
                                <a class="block rounded-2xl border border-primary-700/60 bg-primary-700/40 p-5 transition hover:border-secondary/30 hover:bg-primary-700/70"
                                    href="{{ $item['url'] }}">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-on-primary">{{ $item['title'] }}</p>
                                            <p class="mt-2 text-sm text-primary-100">{{ $item['subtitle'] }}</p>
                                            @if ($item['meta'])
                                                <p class="mt-3 text-xs text-primary-200">{{ $item['meta'] }}</p>
                                            @endif
                                        </div>
                                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                                    </div>
                                </a>
                            @endforeach
                            @if ($group['items'] instanceof \Illuminate\Pagination\LengthAwarePaginator)
                                <x-panel.pagination :paginator="$group['items']" />
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
@endsection
