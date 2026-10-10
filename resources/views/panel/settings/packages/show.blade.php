@extends('templates.adminlte')

@php
    $toneBadgeClasses = [
        'secondary' => 'border-secondary/30 bg-secondary/10 text-secondary',
        'accent' => 'border-accent/30 bg-accent/10 text-accent',
        'warning' => 'border-warning/30 bg-warning/10 text-warning',
        'primary' => 'border-primary-600/50 bg-primary-700/60 text-primary-100',
        'error' => 'border-error/30 bg-error/10 text-error',
    ];

    $toneTextClasses = [
        'secondary' => 'text-secondary',
        'accent' => 'text-accent',
        'warning' => 'text-warning',
        'primary' => 'text-primary-200',
        'error' => 'text-error',
    ];
@endphp

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col align-self-end">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('settings.packages.index') }}">Paquetes</a></li>
                        <li class="breadcrumb-item active">{{ $package->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @if (app(\App\Services\Inventory\InventoryAccess::class)->allows(auth()->user(), 'events.view'))
    <div class="mx-auto mt-16 max-w-[1680px] px-4 pt-6 sm:px-8">
        <a href="{{ route('settings.packages.requirements', $package->id) }}" class="inline-flex rounded-xl bg-primary-700 px-4 py-3 text-secondary">Requerimientos del paquete</a>
    </div>
    @endif

    <div class="mx-auto mt-16 flex w-full max-w-[1680px] flex-col gap-8 px-4 py-6 sm:p-8 xl:flex-row xl:items-start">
        <div class="min-w-0 flex-1 space-y-6 xl:flex-[1.2]">
            <section class="relative overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/20">
                <div class="absolute inset-0 z-0">
                    <img alt="{{ $package->name }}" class="h-full w-full object-cover opacity-20"
                        data-alt="Spectacular golden fireworks exploding against a pitch black night sky, vibrant orange and gold sparks filling the frame with dynamic energy"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBau5YAA57V7_U7YJ5LanXoYjgw-fIY7B-KvOaUoZ3o7RZMIuXki__s90j3Pducw3tb57Yn5-4odgxT56nmH22AkSLv7UQE6e0y6Bi6vnkZ--xpJuENpoiNYxJUiZgCEUQOaPdbZtgX4yMzlDDNUGg8p3YaykpkVHcor7fi1NcDQeX3E5bRPBLOArndW-T9JEsAE9JHlbfDB6MRKP85kZjfN2LLJf1VTLnWfbSKbzTfNRveEvIuKjQpTaF2oNZzEZ1nxaOuQpEtw3A" />
                    <div class="absolute inset-0 bg-gradient-to-t from-primary-900 via-primary-900/85 to-transparent"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-900 via-primary-800/90 to-transparent"></div>
                </div>

                <div class="relative z-10 flex flex-col gap-8 p-8 md:flex-row md:items-start md:justify-between lg:p-10">
                    <div class="max-w-2xl space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wider {{ $toneBadgeClasses[$statusMeta['tone']] ?? $toneBadgeClasses['primary'] }}">
                                {{ $statusMeta['label'] }}
                            </span>
                            <span class="font-mono text-sm text-primary-200">{{ $packageCode }}</span>
                        </div>

                        <h2 class="font-heading text-4xl font-extrabold tracking-tight text-on-primary md:text-5xl">
                            {{ $package->name }}
                        </h2>

                        <p class="max-w-2xl text-sm leading-7 text-primary-100">
                            {{ $packageMeta['description'] }}
                        </p>

                        <div class="flex flex-wrap items-end gap-6 pt-4">
                            <div>
                                <p class="mb-1 text-sm font-semibold uppercase tracking-widest text-primary-200">
                                    Precio Base
                                </p>
                                <p class="font-heading text-3xl font-bold text-warning">{{ $packageMeta['formattedPrice'] }}
                                </p>
                            </div>
                            <div class="hidden h-12 w-px bg-primary-600/40 sm:block"></div>
                            <div>
                                <p class="mb-1 text-sm font-semibold uppercase tracking-widest text-primary-200">
                                    Duración
                                </p>
                                <p class="flex items-center gap-2 font-heading text-2xl font-bold text-on-primary">
                                    <span class="material-symbols-outlined text-accent">timer</span>
                                    {{ $packageMeta['duration'] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full flex-wrap gap-3 md:w-auto md:max-w-xs md:flex-col md:items-stretch">
                        <a href="{{ route('sales.create') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-secondary px-6 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600 md:justify-start">
                            <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                            Usar en Venta
                        </a>
                        <a href="{{ route('packages.edit', $package->id) }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-on-primary/10 px-6 py-3 text-sm font-medium text-on-primary transition-colors hover:bg-on-primary/20 md:justify-start">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                            Editar
                        </a>
                        <div class="flex w-full gap-2">
                            <button
                                class="flex flex-1 justify-center rounded-xl bg-on-primary/10 p-2.5 text-on-primary transition-colors hover:bg-on-primary/20"
                                title="Duplicar" type="button">
                                <span class="material-symbols-outlined text-[20px]">content_copy</span>
                            </button>
                            <button
                                class="flex flex-1 justify-center rounded-xl bg-on-primary/10 p-2.5 text-on-primary transition-colors hover:bg-on-primary/20"
                                title="Imprimir Ficha" type="button">
                                <span class="material-symbols-outlined text-[20px]">print</span>
                            </button>
                            <button
                                class="flex flex-1 justify-center rounded-xl bg-error/10 p-2.5 text-error transition-colors hover:bg-error/20"
                                title="Desactivar" type="button">
                                <span class="material-symbols-outlined text-[20px]">block</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/10 md:col-span-2">
                    <h3 class="mb-6 border-b border-primary-700/60 pb-4 font-heading text-xl font-bold text-on-primary">
                        Información General
                    </h3>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                    Nombre Comercial
                                </p>
                                <p class="font-medium text-on-primary">{{ $package->name }}</p>
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                    SKU Interno
                                </p>
                                <p class="font-mono text-on-primary">{{ $packageCode }}</p>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                Descripción
                            </p>
                            <p class="text-sm leading-relaxed text-primary-100">{{ $packageMeta['description'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/10">
                    <h3 class="mb-6 border-b border-primary-700/60 pb-4 font-heading text-xl font-bold text-on-primary">
                        Tipos de Evento
                    </h3>

                    <div class="flex flex-wrap gap-2">
                        @forelse ($eventTypeBadges as $badge)
                            <span
                                class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium {{ $toneBadgeClasses[$badge['tone']] ?? $toneBadgeClasses['primary'] }}">
                                <span class="material-symbols-outlined text-[16px]">{{ $badge['icon'] }}</span>
                                {{ $badge['name'] }}
                            </span>
                        @empty
                            <div
                                class="rounded-2xl border border-primary-700/60 bg-primary-900/30 p-4 text-sm text-primary-200">
                                Este paquete aún no tiene historial de uso suficiente para inferir tipos de evento.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <section>
                <div class="mb-6 flex items-center justify-between gap-4">
                    <h3 class="font-heading text-2xl font-bold text-on-primary">Productos Incluidos</h3>
                    <span
                        class="rounded-full border border-primary-700/60 bg-primary-800 px-3 py-1 text-sm font-medium text-primary-200">
                        {{ number_format($packageItems->count()) }} items
                    </span>
                </div>

                <div class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/10">
                    <table
                        class="responsive-stack-table responsive-stack-table-dark min-w-[860px] w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-primary-700/60 bg-primary-900/40">
                                <th class="p-4 text-xs font-semibold uppercase tracking-widest text-primary-200">Producto
                                </th>
                                <th class="p-4 text-xs font-semibold uppercase tracking-widest text-primary-200">Categoría
                                </th>
                                <th class="p-4 text-right text-xs font-semibold uppercase tracking-widest text-primary-200">
                                    Cant.</th>
                                <th class="p-4 text-right text-xs font-semibold uppercase tracking-widest text-primary-200">
                                    Unidad</th>
                                <th class="p-4 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                    Observaciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-primary-700/40">
                            @forelse ($packageItems as $item)
                                <tr class="transition-colors hover:bg-primary-700/30">
                                    <td class="flex items-center gap-4 p-4">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-primary-700/60 bg-primary-900/40">
                                            <span
                                                class="material-symbols-outlined {{ $toneTextClasses[$item['tone']] ?? $toneTextClasses['primary'] }}">
                                                {{ $item['icon'] }}
                                            </span>
                                        </div>
                                        <span class="font-medium text-on-primary">{{ $item['name'] }}</span>
                                    </td>
                                    <td class="p-4">
                                        <span
                                            class="inline-flex rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] {{ $toneBadgeClasses[$item['tone']] ?? $toneBadgeClasses['primary'] }}">
                                            {{ $item['category'] }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right text-sm font-mono text-on-primary">{{ $item['quantity'] }}
                                    </td>
                                    <td class="p-4 text-right text-sm text-primary-200">{{ $item['unit'] }}</td>
                                    <td class="p-4 text-sm text-primary-200">{{ $item['observations'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-8 text-sm text-primary-200" colspan="5">
                                        Este paquete todavía no tiene productos o materiales vinculados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>


            <div class="rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/10">
                <h3 class="mb-6 border-b border-primary-700/60 pb-4 font-heading text-xl font-bold text-on-primary">
                    Equipamiento Requerido
                </h3>

                <ul class="space-y-4">
                    @forelse ($equipmentRows as $equipment)
                        <li
                            class="flex items-center justify-between gap-4 rounded-2xl border border-primary-700/60 bg-primary-900/40 p-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="material-symbols-outlined {{ $toneTextClasses[$equipment['tone']] ?? $toneTextClasses['primary'] }}">
                                    {{ $equipment['icon'] }}
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-on-primary">{{ $equipment['name'] }}</p>
                                    <p class="text-xs text-primary-200">{{ $equipment['description'] }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="block text-sm font-mono text-on-primary">
                                    {{ $equipment['quantity'] }} {{ Str::lower($equipment['unit']) }}
                                </span>
                                <span class="text-xs text-primary-200">Requerido por paquete</span>
                            </div>
                        </li>
                    @empty
                        <li
                            class="rounded-2xl border border-primary-700/60 bg-primary-900/30 p-4 text-sm text-primary-200">
                            No hay equipamiento vinculado todavía.
                        </li>
                    @endforelse
                </ul>
            </div>



        </div>

        <aside class="space-y-6 xl:w-full xl:max-w-[420px] xl:flex-[0.8]">
            <div class="space-y-6 xl:sticky xl:top-24">

                <div
                    class="relative overflow-hidden rounded-3xl border border-primary/20 bg-primary-800 p-8 shadow-2xl shadow-primary-900/10">
                    <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-secondary/10 blur-3xl"></div>
                    <h3 class="mb-6 border-b border-primary-700/60 pb-4 font-heading text-xl font-bold text-on-primary">
                        Nivel de Experiencia
                    </h3>
                    <div class="mb-4 flex items-start gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-primary/30 bg-primary/10">
                            <span class="material-symbols-outlined text-2xl text-primary">workspace_premium</span>
                        </div>
                        <div>
                            <h4 class="mb-1 text-lg font-bold text-secondary">{{ $packageMeta['experienceName'] }}</h4>
                            <p class="text-sm leading-relaxed text-primary-200">
                                {{ $packageMeta['experienceDescription'] }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="mt-6 rounded-2xl border-l-2 border-secondary bg-primary-900/40 p-4 text-xs text-primary-100">
                        <strong>Referencia Operativa:</strong>
                        {{ $package->events_count > 0 ? 'Este nivel se respalda con el historial real de eventos vinculados al paquete.' : 'Aún no hay suficiente historial operativo para enriquecer más este bloque.' }}
                    </div>
                </div>
                <div class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <h3 class="mb-4 font-heading text-lg font-bold text-on-primary">Resumen Ejecutivo</h3>

                    <div class="space-y-5">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                Veces reservado
                            </p>
                            <div class="flex items-end gap-2">
                                <span class="font-heading text-3xl font-bold text-on-primary">
                                    {{ $executiveSummary['usageCount'] }}
                                </span>
                                <span
                                    class="mb-1 flex items-center gap-1 text-sm font-medium {{ $toneTextClasses[$executiveSummary['usageTrendTone']] ?? $toneTextClasses['primary'] }}">
                                    <span class="material-symbols-outlined text-[16px]">
                                        {{ $executiveSummary['usageTrendIcon'] }}
                                    </span>
                                    {{ $executiveSummary['usageTrendLabel'] }}
                                </span>
                            </div>
                        </div>

                        <div class="h-px w-full bg-primary-700/60"></div>

                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                {{ $executiveSummary['activityLabel'] }}
                            </p>
                            <p class="font-medium text-on-primary">{{ $executiveSummary['activityValue'] }}</p>
                            <p class="mt-0.5 text-xs text-primary-200">{{ $executiveSummary['activityDetail'] }}</p>
                        </div>

                        <div class="h-px w-full bg-primary-700/60"></div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-primary-200">
                                Paquete Similar
                            </p>

                            @if ($similarPackage)
                                <a class="group flex items-center justify-between rounded-xl p-2 transition-colors hover:bg-primary-700/30"
                                    href="{{ $similarPackage['url'] }}">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-900/40">
                                            <span class="material-symbols-outlined text-[18px] text-secondary">star</span>
                                        </div>
                                        <div>
                                            <span
                                                class="block text-sm font-medium text-on-primary transition-colors group-hover:text-secondary">
                                                {{ $similarPackage['name'] }}
                                            </span>
                                            <span class="text-xs text-primary-200">{{ $similarPackage['hint'] }}</span>
                                        </div>
                                    </div>
                                    <span
                                        class="material-symbols-outlined text-[18px] text-primary-200 group-hover:text-secondary">
                                        arrow_forward
                                    </span>
                                </a>
                            @else
                                <div
                                    class="rounded-2xl border border-primary-700/60 bg-primary-900/30 p-4 text-sm text-primary-200">
                                    No hay otro paquete comparable en el catálogo actual.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/10">
                    <h3 class="mb-4 font-heading text-lg font-bold text-on-primary">Historial de Versiones</h3>

                    <div
                        class="relative space-y-6 pl-4 before:absolute before:inset-y-2 before:left-[7px] before:w-0.5 before:bg-primary-700/50">
                        @foreach ($historyEntries as $entry)
                            <div class="relative">
                                <div
                                    class="absolute -left-[21px] top-1 h-3.5 w-3.5 rounded-full {{ $entry['tone'] === 'secondary' ? 'bg-secondary ring-4 ring-primary-800' : 'bg-primary-600 ring-4 ring-primary-800' }}">
                                </div>
                                <div class="mb-1 flex items-center justify-between gap-4">
                                    <span
                                        class="text-sm font-bold {{ $toneTextClasses[$entry['tone']] ?? $toneTextClasses['primary'] }}">
                                        {{ $entry['label'] }}
                                    </span>
                                    <span class="font-mono text-xs text-primary-200">{{ $entry['priceLabel'] }}</span>
                                </div>
                                <p class="mb-1 text-xs text-primary-200">{{ $entry['dateLabel'] }}</p>
                                <p
                                    class="mt-2 rounded-lg border border-primary-700/60 bg-primary-900/40 p-2 text-xs text-primary-100">
                                    {{ $entry['note'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <div
                        class="mt-4 rounded-xl border border-primary-700/60 bg-primary-900/20 px-4 py-3 text-sm text-primary-200">
                        El esquema actual no conserva snapshots históricos por versión; esta sección refleja únicamente las
                        marcas de tiempo reales disponibles en la base.
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
