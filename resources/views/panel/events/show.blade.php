@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Evento</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">Eventos</a></li>
                        <li class="breadcrumb-item active">Evento</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
@endsection

@section('main-content')
    @php
        $eventDate = \Carbon\Carbon::parse($event->event_date, 'America/Mexico_City');
        $formatCurrency = fn($amount) => '$' . number_format((float) $amount, 2);
        $statusLabel = $eventDate->isPast() ? 'Finalizado' : ($eventDate->isToday() ? 'Hoy' : 'Programado');
        $roleLabels = [
            1 => 'Producto',
            2 => 'Material',
            3 => 'Producto paquete',
        ];
        $packageDuration = $event->packages->pluck('duration')->filter()->implode(' · ');
        $packageUnits = (int) $event->packages->sum(fn($package) => max((int) ($package->pivot->quantity ?? 1), 1));
        $packageMeta = $event->is_custom_event
            ? 'Paquete personalizado'
            : ($packageUnits > 1
                ? $packageUnits . ' paquetes registrados'
                : 'Paquete registrado');
        $packageSummary =
            $packageDuration !== ''
                ? $packageMeta . ' • ' . $packageDuration
                : ($event->is_custom_event
                    ? $packageMeta . ' • ' . $event->products->count() . ' productos programados'
                    : $packageMeta . ' • Duración no registrada');
        $discountValue = (float) $event->discount;
        $discountLabel = 'Descuento aplicado';
        $quoteDateLabel = filled($event->date)
            ? \Carbon\Carbon::parse($event->date, 'America/Mexico_City')->locale('es')->isoFormat('D MMM YYYY')
            : 'Sin fecha de cotización';
        $createdAtLabel = $event->created_at
            ? $event->created_at->copy()->locale('es')->isoFormat('D MMM YYYY, HH:mm')
            : 'Sin registro';
        $crewCount = $event->employees->count();
        $productCount = $event->products->count();
        $equipmentCount = $event->equipments->count();
        $packageBadgeClass = $event->is_custom_event
            ? 'border-secondary/30 bg-secondary/10 text-secondary'
            : 'border-accent/30 bg-accent/10 text-accent';

        if ($discountValue > 0 && $discountValue <= 1) {
            $discountLabel .= ' (' . number_format($discountValue * 100, 0) . '%)';
        }

        if ($statusLabel === 'Hoy') {
            $statusBadgeClass = 'border-warning/30 bg-warning/10 text-warning';
            $statusDotClass = 'bg-warning';
        } elseif ($statusLabel === 'Finalizado') {
            $statusBadgeClass = 'border-primary-600/60 bg-primary-700/70 text-primary-100';
            $statusDotClass = 'bg-primary-200';
        } else {
            $statusBadgeClass = 'border-accent/30 bg-accent/10 text-accent';
            $statusDotClass = 'bg-accent';
        }
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1600px] space-y-8 px-4 py-6 sm:p-8">
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.85fr)]">
            <div class="relative overflow-hidden rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div class="absolute -left-10 top-10 h-40 w-40 rounded-full bg-accent/10 blur-3xl"></div>
                <div class="absolute -right-10 -top-12 h-48 w-48 rounded-full bg-secondary/10 blur-3xl"></div>
                <div class="relative flex h-full flex-col justify-between gap-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                            {{ $event->event_code }}
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] {{ $statusBadgeClass }}">
                            <span class="h-2 w-2 rounded-full {{ $statusDotClass }}"></span>
                            {{ $statusLabel }}
                        </span>
                        <span
                            class="inline-flex rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] {{ $packageBadgeClass }}">
                            {{ $event->is_custom_event ? 'Personalizado' : 'Registrado' }}
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-secondary">Detalle Operativo</p>
                        <h2 class="mt-4 font-heading text-4xl font-bold tracking-tight text-on-primary xl:text-5xl">
                            {{ $event->client_name ?: 'Cliente sin registrar' }}
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-primary-200">
                            {{ $event->event_type }}. {{ $event->package_label }}.
                            {{ $event->notes ?: 'Sin observaciones logísticas registradas para este servicio.' }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-secondary">calendar_month</span>
                                {{ $event->event_day_label }}
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-accent">schedule</span>
                                {{ $event->event_time_label }}
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-warning">location_on</span>
                                {{ $event->event_address ?: 'Ubicación pendiente' }}
                            </div>
                            <div
                                class="inline-flex items-center gap-2 rounded-xl border border-primary-600/50 bg-primary-700/60 px-4 py-3 text-sm text-primary-100">
                                <span class="material-symbols-outlined text-secondary">call</span>
                                {{ $event->phone ?: 'Sin teléfono' }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Total evento
                            </p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">{{ $formatCurrency($event->total_amount) }}
                            </p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Anticipo</p>
                            <p class="mt-3 text-3xl font-bold text-on-primary">
                                {{ $formatCurrency($event->advance_amount) }}</p>
                        </article>
                        <article class="rounded-2xl border border-primary-600/40 bg-primary-700/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Saldo
                                pendiente</p>
                            <p class="mt-3 text-3xl font-bold text-secondary">{{ $formatCurrency($event->balance) }}</p>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Centro de Acciones</p>
                <h3 class="mt-3 text-3xl font-bold text-on-primary">Gestion del evento</h3>
                <p class="mt-2 text-sm text-primary-100">
                    Contrato, hoja operativa y recordatorios desde una misma vista alineada al panel.
                </p>

                <div class="mt-8 grid gap-3">
                    <a href="{{ route('showByWhatsapp', $event->id) }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20"
                        rel="noreferrer" target="_blank">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-secondary">print_connect</span>
                            Imprimir hoja encargado
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>

                    <a href="{{ route('showContract', $event->id) }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20"
                        rel="noreferrer" target="_blank">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-accent">contract</span>
                            Imprimir contrato
                        </span>
                        <span class="material-symbols-outlined text-primary-200">arrow_outward</span>
                    </a>

                    <form action="{{ route('events.send-reminder', $event->id) }}" method="POST">
                        @csrf
                        <button
                            class="flex w-full items-center justify-between rounded-2xl bg-on-primary/10 px-5 py-4 text-sm font-semibold text-on-primary transition-colors hover:bg-on-primary/20"
                            type="submit">
                            <span class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-warning">send_time_extension</span>
                                Enviar recordatorio
                            </span>
                            <span class="material-symbols-outlined text-primary-200">north_east</span>
                        </button>
                    </form>

                    <a href="{{ route('events.edit', $event->id) }}"
                        class="inline-flex items-center justify-between rounded-2xl bg-secondary px-5 py-4 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-[0.99]">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined">edit</span>
                            Editar evento
                        </span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>

                    {{-- <button
                        class="flex items-center justify-between rounded-2xl bg-accent px-5 py-4 text-sm font-bold text-on-accent shadow-lg shadow-accent/20 transition-all hover:bg-accent-600 active:scale-[0.99]"
                        type="button">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined">payments</span>
                            Registrar cobro
                        </span>
                        <span class="material-symbols-outlined">add_card</span>
                    </button> --}}
                </div>

                <div class="mt-8 rounded-2xl bg-on-primary/10 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Resumen rapido</p>
                    <div class="mt-4 space-y-3 text-sm text-primary-100">
                        <div class="flex items-center justify-between gap-4">
                            <span>Productos programados</span>
                            <span class="font-semibold text-on-primary">{{ $productCount }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span>Equipo asignado</span>
                            <span class="font-semibold text-on-primary">{{ $crewCount }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span>Equipamiento requerido</span>
                            <span class="font-semibold text-on-primary">{{ $equipmentCount }}</span>
                        </div>
                        <div class="h-px bg-on-primary/10"></div>
                        <div class="flex items-center justify-between gap-4">
                            <span>Fecha operativa</span>
                            <span class="font-semibold text-on-primary">{{ $event->event_datetime_label }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
            <div class="space-y-8 lg:col-span-8">
                <section class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-secondary/10 text-secondary">
                            <span class="material-symbols-outlined">info</span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Informacion General
                            </p>
                            <h3 class="mt-1 text-2xl font-bold text-on-primary">Cliente y logistica</h3>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Datos del
                                cliente</p>
                            <div class="space-y-3">
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                    <p class="text-xs text-primary-200">Nombre</p>
                                    <p class="mt-2 text-sm font-semibold text-on-primary">
                                        {{ $event->client_name ?: 'Sin nombre' }}</p>
                                </div>
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                    <p class="text-xs text-primary-200">Telefono</p>
                                    <p class="mt-2 text-sm font-semibold text-on-primary">
                                        {{ $event->phone ?: 'Sin telefono' }}</p>
                                </div>
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                    <p class="text-xs text-primary-200">Direccion</p>
                                    <p class="mt-2 text-sm font-semibold text-on-primary">
                                        {{ $event->client_address ?: 'Sin direccion registrada' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-200">Operacion del
                                evento</p>
                            <div class="space-y-3">
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                    <p class="text-xs text-primary-200">Ubicacion</p>
                                    <p class="mt-2 text-sm font-semibold text-on-primary">
                                        {{ $event->event_address ?: 'Pendiente' }}</p>
                                </div>
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                    <p class="text-xs text-primary-200">Hora operativa</p>
                                    <p class="mt-2 text-sm font-semibold text-on-primary">{{ $event->event_time_label }}
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                    <p class="text-xs text-primary-200">Observaciones</p>
                                    <p class="mt-2 text-sm font-semibold text-on-primary">
                                        {{ $event->notes ?: 'Sin observaciones logísticas registradas.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/20">
                    <div
                        class="flex flex-col gap-4 border-b border-primary-700/60 px-6 py-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Inventario Programado
                            </p>
                            <h3 class="mt-1 text-2xl font-bold text-on-primary">Productos del evento</h3>
                        </div>
                        <button
                            class="inline-flex items-center gap-2 text-sm font-semibold text-accent transition-colors hover:text-accent-300"
                            type="button">
                            <span class="material-symbols-outlined">inventory_2</span>
                            Gestionar stock
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="responsive-stack-table responsive-stack-table-dark min-w-full text-left">
                            <thead>
                                <tr class="border-b border-primary-700/60 bg-primary-700/40">
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-[0.22em] text-primary-200">
                                        Producto</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-[0.22em] text-primary-200">
                                        Categoria</th>
                                    <th
                                        class="px-6 py-4 text-center text-xs font-bold uppercase tracking-[0.22em] text-primary-200">
                                        Prog.</th>
                                    <th
                                        class="px-6 py-4 text-center text-xs font-bold uppercase tracking-[0.22em] text-primary-200">
                                        Usado</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-[0.22em] text-primary-200">
                                        Obs.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-primary-700/60">
                                @forelse ($event->products as $product)
                                    @php
                                        $descriptor = collect([
                                            $product->caliber ? $product->caliber . '"' : null,
                                            $product->shots ? $product->shots . ' tiros' : null,
                                            $product->shape ?: null,
                                            $product->unit ?: null,
                                        ])
                                            ->filter()
                                            ->implode(' · ');
                                        $quantity = (int) ($product->pivot->quantity ?? 0);
                                        $observations =
                                            $product->description ?:
                                            ($descriptor !== ''
                                                ? $descriptor
                                                : 'Sin observaciones registradas.');
                                    @endphp
                                    <tr class="transition-colors hover:bg-primary-700/35">
                                        <td class="px-6 py-5" data-label="Producto">
                                            <p class="font-semibold text-on-primary">{{ $product->name }}</p>
                                        </td>
                                        <td class="px-6 py-5" data-label="Categoría">
                                            <span
                                                class="inline-flex rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-primary-100">
                                                {{ $roleLabels[$product->product_role_id] ?? 'Producto' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-center text-sm font-semibold text-on-primary" data-label="Programado">
                                            {{ $quantity }} {{ $product->unit ?: 'pz' }}
                                        </td>
                                        <td class="px-6 py-5 text-center text-sm font-semibold text-secondary" data-label="Usado">--</td>
                                        <td class="px-6 py-5 text-sm text-primary-200" data-label="Observaciones">{{ $observations }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-6 py-10 text-center text-sm text-primary-200" colspan="5">
                                            Este evento no tiene productos programados.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="space-y-8 lg:col-span-4">
                <section class="relative overflow-hidden rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-secondary/10 blur-2xl"></div>
                    <div class="relative">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-warning/10 text-warning">
                                <span class="material-symbols-outlined">account_balance_wallet</span>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-warning">Liquidacion</p>
                                <h3 class="mt-1 text-2xl font-bold text-on-primary">Cobro pendiente</h3>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-primary-200">Subtotal paquete</span>
                                <span
                                    class="font-semibold text-on-primary">{{ $formatCurrency($event->full_price) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-primary-200">Viaticos / traslado</span>
                                <span
                                    class="font-semibold text-on-primary">{{ $formatCurrency($event->travel_expenses_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-primary-200">{{ $discountLabel }}</span>
                                <span class="font-semibold text-warning-200">
                                    {{ $event->discount_amount > 0 ? '-' . $formatCurrency($event->discount_amount) : $formatCurrency(0) }}
                                </span>
                            </div>
                            <div class="h-px bg-primary-700/60"></div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-primary-200">Total final</span>
                                <span
                                    class="text-lg font-bold text-on-primary">{{ $formatCurrency($event->total_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-primary-200">Anticipo pagado</span>
                                <span class="font-semibold text-accent-200">
                                    {{ $event->advance_amount > 0 ? '-' . $formatCurrency($event->advance_amount) : $formatCurrency(0) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-6 rounded-2xl bg-secondary p-5 text-on-secondary">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em]">Saldo a cobrar</p>
                                    <p class="mt-2 text-3xl font-black">{{ $formatCurrency($event->balance) }}</p>
                                </div>
                                <span class="material-symbols-outlined text-4xl text-on-secondary/70">contactless</span>
                            </div>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-on-secondary/80">
                                Cobrar en evento: {{ $event->balance > 0 ? 'SI' : 'NO' }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Configuracion Show</p>
                    <div
                        class="mt-5 flex items-center gap-4 rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent/10 text-accent">
                            <span class="material-symbols-outlined">workspace_premium</span>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-on-primary">{{ $event->package_label }}</h4>
                            <p class="mt-1 text-xs text-primary-200">{{ $packageSummary }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary-200">Productos</p>
                            <p class="mt-2 text-2xl font-bold text-on-primary">{{ $productCount }}</p>
                        </div>
                        <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary-200">Equipo</p>
                            <p class="mt-2 text-2xl font-bold text-on-primary">{{ $crewCount }}</p>
                        </div>
                        <div class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary-200">Equipamiento
                            </p>
                            <p class="mt-2 text-2xl font-bold text-on-primary">{{ $equipmentCount }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Staff Asignado</p>
                    <div class="mt-5 space-y-4">
                        @forelse ($event->employees as $employee)
                            @php
                                $employeeName = $employee->user->name ?? ($employee->name ?? 'Empleado sin nombre');
                                $employeePhone = $employee->user->phone ?? ($employee->phone ?? null);
                                $employeePhoto = $employee->photo ?? null;

                                if ($employeePhoto && !preg_match('/^https?:\/\//', $employeePhoto)) {
                                    $employeePhoto = asset($employeePhoto);
                                }

                                if (!$employeePhoto) {
                                    $employeePhoto =
                                        'https://lh3.googleusercontent.com/aida-public/AB6AXuC90cd4RGzirkrgu-fQUOzz68c6LmczKYRfg42rz5aUDkjfqEGpQ_AEZ4DjCtFE4ibGh-9lHbdCUDHWhlrqfSQnLKNfvxdNosSiHicBkve5D8U1JEwvIN4C0UkHhQwK1qsLtPQ9VFgziurtVGwfza3NajwOejBaAo5n4WWenJRy_59t0WLFsklFWvvcvYbMBL8OJ0dke3bJab3aWJgUeodehmnVUY4dzErxlIFkVp5IudDmOYNhTq8QXLwH4VeVh1pFft_IGAKMUK0';
                                }
                            @endphp
                            <div
                                class="flex items-center gap-4 rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4">
                                <img alt="{{ $employeeName }}"
                                    class="h-12 w-12 rounded-full object-cover ring-2 ring-secondary/20"
                                    src="{{ $employeePhoto }}" />
                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-sm font-bold text-on-primary">{{ $employeeName }}</h4>
                                    <p class="mt-1 text-xs text-primary-200">
                                        Sueldo del evento: ${{ number_format((float) ($employee->pivot->salary ?? $employee->salary), 2) }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex rounded-full border border-secondary/30 bg-secondary/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-secondary">
                                            {{ $loop->first ? 'Responsable' : 'Staff' }}
                                        </span>
                                        <span class="text-[11px] text-primary-200">
                                            Nivel: {{ $employee->experienceLevel->name ?? 'Sin nivel' }}
                                        </span>
                                    </div>
                                </div>
                                @if ($employeePhone)
                                    <a class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-700 text-primary-100 transition-colors hover:bg-secondary/10 hover:text-secondary"
                                        href="tel:{{ preg_replace('/\D+/', '', $employeePhone) }}">
                                        <span class="material-symbols-outlined text-sm">call</span>
                                    </a>
                                @else
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-700 text-primary-300/60">
                                        <span class="material-symbols-outlined text-sm">call</span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div
                                class="rounded-2xl border border-primary-700/60 bg-primary-700/40 p-4 text-sm text-primary-200">
                                Este evento no tiene staff asignado.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl bg-primary-800 p-6 shadow-2xl shadow-primary-900/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-warning">Historial</p>
                    <div
                        class="relative mt-6 space-y-6 before:absolute before:bottom-2 before:left-[11px] before:top-2 before:w-0.5 before:bg-primary-700/60 before:content-['']">
                        <div class="relative flex items-start gap-4 pl-8">
                            <div
                                class="absolute left-0 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-accent">
                                <span class="material-symbols-outlined text-[14px] text-on-accent">check</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold leading-none text-on-primary">{{ $statusLabel }}</p>
                                <p class="mt-1 text-[11px] text-primary-200">{{ $event->event_datetime_label }}</p>
                            </div>
                        </div>

                        <div class="relative flex items-start gap-4 pl-8">
                            <div
                                class="absolute left-0 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-secondary">
                                <span class="material-symbols-outlined text-[14px] text-on-secondary">payments</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold leading-none text-on-primary">
                                    {{ $event->advance_amount > 0 ? 'Anticipo registrado' : 'Anticipo pendiente' }}
                                </p>
                                <p class="mt-1 text-[11px] text-primary-200">
                                    {{ $event->advance_amount > 0 ? $formatCurrency($event->advance_amount) : 'Sin anticipo capturado' }}
                                </p>
                            </div>
                        </div>

                        <div class="relative flex items-start gap-4 pl-8">
                            <div
                                class="absolute left-0 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-warning">
                                <span class="material-symbols-outlined text-[14px] text-on-warning">description</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold leading-none text-on-primary">
                                    {{ $event->reminder_send_date ? 'Recordatorio programado' : 'Cotizacion generada' }}
                                </p>
                                <p class="mt-1 text-[11px] text-primary-200">
                                    {{ $event->reminder_send_date ? \Carbon\Carbon::parse($event->reminder_send_date, 'America/Mexico_City')->locale('es')->isoFormat('D MMM YYYY, HH:mm') : $quoteDateLabel }}
                                </p>
                            </div>
                        </div>

                        <div class="relative flex items-start gap-4 pl-8">
                            <div
                                class="absolute left-0 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary-700 text-primary-200">
                                <span class="material-symbols-outlined text-[14px]">add</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold leading-none text-on-primary">Creado</p>
                                <p class="mt-1 text-[11px] text-primary-200">{{ $createdAtLabel }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
