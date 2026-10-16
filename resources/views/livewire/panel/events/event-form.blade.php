@php
    $sectionClass = 'rounded-3xl border border-primary-700/60 bg-primary-900/40 p-6 shadow-soft';
    $sectionHeaderClass =
        'mb-6 flex flex-col gap-3 border-b border-primary-700/60 pb-4 lg:flex-row lg:items-start lg:justify-between';
    $labelClass = 'text-[11px] font-semibold uppercase tracking-[0.24em] text-primary-200';
    $inputClass =
        'w-full rounded-2xl border border-primary-600/40 bg-primary-900/70 px-4 py-3 text-sm text-on-primary placeholder:text-primary-300 transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20';
    $errorClass = 'text-xs font-semibold text-red-400';
    $fieldClass = fn ($field) => $errors->has($field)
        ? str_replace(
            ['border-primary-600/40', 'focus:border-accent', 'focus:ring-accent/20'],
            ['border-red-500', 'focus:border-red-500', 'focus:ring-red-500/20'],
            $inputClass
        )
        : $inputClass;
@endphp

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl border border-primary-700/60 bg-primary-900/40 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Modo</p>
            <p class="mt-3 text-2xl font-bold text-on-primary">{{ $isEditMode ? 'Edición' : 'Nuevo registro' }}</p>
            <p class="mt-2 text-sm text-primary-200">Flujo {{ $isEditMode ? 'de actualización' : 'de alta comercial' }}
                activo.</p>
        </article>
        <article class="rounded-2xl border border-primary-700/60 bg-primary-900/40 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Paquetes registrados</p>
            <p class="mt-3 text-2xl font-bold text-on-primary">{{ $packages->count() }}</p>
            <p class="mt-2 text-sm text-primary-200">opciones comerciales listas para asociar.</p>
        </article>
        <article class="rounded-2xl border border-primary-700/60 bg-primary-900/40 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Personal disponible</p>
            <p class="mt-3 text-2xl font-bold text-on-primary">{{ $employees->count() }}</p>
            <p class="mt-2 text-sm text-primary-200">colaboradores para asignar al operativo.</p>
        </article>
    </div>

    <form class="space-y-6" method="POST" wire:submit.prevent="save">
        @csrf

        @if ($errors->any())
            <div class="rounded-2xl border border-red-500 bg-red-500/10 p-4 text-sm font-semibold text-red-400" role="alert">
                Revisa los campos marcados en rojo antes de continuar.
            </div>
        @endif

        <section class="{{ $sectionClass }}">
            <div class="{{ $sectionHeaderClass }}">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Cliente</p>
                    <h4 class="mt-2 text-xl font-bold text-on-primary">Datos de contacto</h4>
                </div>
                <p class="max-w-xl text-sm text-primary-200">
                    Registra la información base para identificar rápidamente al cliente y mantener trazabilidad del
                    contrato.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="{{ $labelClass }}" for="phone">Teléfono <span class="text-red-400">*</span></label>
                    <input class="{{ $fieldClass('phone') }}" id="phone" placeholder="55 0000 0000" type="text"
                        wire:model="phone" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                    @error('phone')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="{{ $labelClass }}" for="client_name">Nombre del cliente <span class="text-red-400">*</span></label>
                    <input class="{{ $fieldClass('client_name') }}" id="client_name" placeholder="Ej. Juan Pérez" type="text"
                        wire:model="client_name" aria-invalid="{{ $errors->has('client_name') ? 'true' : 'false' }}">
                    @error('client_name')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="{{ $labelClass }}" for="client_address">Dirección del cliente</label>
                    <input class="{{ $fieldClass('client_address') }}" id="client_address"
                        placeholder="Calle, número, colonia y ciudad del cliente" type="text"
                        wire:model="client_address" aria-invalid="{{ $errors->has('client_address') ? 'true' : 'false' }}">
                    @error('client_address')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="{{ $sectionClass }}">
            <div class="{{ $sectionHeaderClass }}">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Programación</p>
                    <h4 class="mt-2 text-xl font-bold text-on-primary">Datos del evento</h4>
                </div>
                <p class="max-w-xl text-sm text-primary-200">
                    Define fecha de registro, agenda del evento y la ubicación operativa que usará el equipo.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-2">
                    <label class="{{ $labelClass }}" for="date">Fecha de registro</label>
                    <input class="{{ $fieldClass('date') }}" id="date" type="date" wire:model="date" disabled aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}">
                    @error('date')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="{{ $labelClass }}" for="event_date">Fecha del evento <span class="text-red-400">*</span></label>
                    <input class="{{ $fieldClass('event_date') }}" id="event_date" type="date" wire:model="event_date" aria-invalid="{{ $errors->has('event_date') ? 'true' : 'false' }}">
                    <p class="text-xs text-primary-200">
                        Si capturas una fecha anterior a hoy, el sistema te pedirá confirmación antes de guardar.
                    </p>
                    @error('event_date')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="{{ $labelClass }}" for="event_time">Hora del evento <span class="text-red-400">*</span></label>
                    <input class="{{ $fieldClass('event_time') }}" id="event_time" type="time" wire:model="event_time" aria-invalid="{{ $errors->has('event_time') ? 'true' : 'false' }}">
                    @error('event_time')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label class="{{ $labelClass }}" for="event_type_id">Tipo de evento <span class="text-red-400">*</span></label>
                    <select class="{{ $fieldClass('event_type_id') }} appearance-none" id="event_type_id"
                        wire:model.live="event_type_id" aria-invalid="{{ $errors->has('event_type_id') ? 'true' : 'false' }}">
                        <option value="">Seleccione un tipo de evento</option>
                        @foreach ($eventTypes as $eventType)
                            <option value="{{ $eventType->id }}">{{ $eventType->name }}</option>
                        @endforeach
                    </select>
                    @error('event_type_id')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2 md:col-span-2 xl:col-span-4">
                    <label class="{{ $labelClass }}" for="event_address">Dirección del evento <span class="text-red-400">*</span></label>
                    <input class="{{ $fieldClass('event_address') }}" id="event_address"
                        placeholder="Ubicación exacta donde se montará el espectáculo" type="text"
                        wire:model="event_address" aria-invalid="{{ $errors->has('event_address') ? 'true' : 'false' }}">
                    @error('event_address')
                        <p class="{{ $errorClass }}">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="{{ $sectionClass }}">
            <div class="{{ $sectionHeaderClass }}">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-warning">Modalidad</p>
                    <h4 class="mt-2 text-xl font-bold text-on-primary">Cómo se construirá este evento</h4>
                </div>
                <p class="max-w-xl text-sm text-primary-200">
                    Elige si trabajarás con paquetes registrados o con una composición personalizada que solo viva
                    dentro de este evento.
                </p>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <button @class([
                    'rounded-2xl border p-6 text-left transition-all',
                    'border-secondary bg-secondary/10 shadow-lg shadow-secondary/10' =>
                        $packageMode === 'registered',
                    'border-primary-700/60 bg-primary-800/60 hover:border-primary-500/60' =>
                        $packageMode !== 'registered',
                    'cursor-not-allowed opacity-55' => $packages->isEmpty(),
                ])
                    @if ($packages->isNotEmpty()) wire:click="$set('packageMode', 'registered')" @endif
                    type="button">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Opción 1</p>
                            <h5 class="mt-2 text-lg font-bold text-on-primary">Paquete registrado</h5>
                            <p class="mt-2 text-sm leading-6 text-primary-200">
                                Usa uno o varios paquetes ya dados de alta y, si hace falta, complementa con selección
                                de materiales variables.
                            </p>
                        </div>
                        <span
                            class="material-symbols-outlined {{ $packageMode === 'registered' ? 'text-secondary' : 'text-primary-200' }}">
                            inventory_2
                        </span>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center gap-3 text-xs font-semibold">
                        <span
                            class="rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-primary-100">
                            {{ $packages->count() }} disponibles
                        </span>
                        @if ($packages->isEmpty())
                            <span class="rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-warning">
                                Sin catálogo cargado
                            </span>
                        @endif
                    </div>
                </button>

                <button @class([
                    'rounded-2xl border p-6 text-left transition-all',
                    'border-accent bg-accent/10 shadow-lg shadow-accent/10' =>
                        $packageMode === 'custom',
                    'border-primary-700/60 bg-primary-800/60 hover:border-primary-500/60' =>
                        $packageMode !== 'custom',
                ]) type="button" wire:click="$set('packageMode', 'custom')">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Opción 2</p>
                            <h5 class="mt-2 text-lg font-bold text-on-primary">Paquete personalizado</h5>
                            <p class="mt-2 text-sm leading-6 text-primary-200">
                                Construye el evento agregando productos reales del catálogo. No se registrará un
                                `Package`; solo se guardará la composición del evento.
                            </p>
                        </div>
                        <span
                            class="material-symbols-outlined {{ $packageMode === 'custom' ? 'text-accent' : 'text-primary-200' }}">
                            auto_awesome_motion
                        </span>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center gap-3 text-xs font-semibold">
                        <span class="rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-accent">
                            Flexible y sin catálogo
                        </span>
                        <span
                            class="rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-primary-100">
                            Guarda materiales directos
                        </span>
                    </div>
                </button>
            </div>
        </section>

        @if ($packageMode === 'registered')
            <section class="{{ $sectionClass }}">
                <div class="{{ $sectionHeaderClass }}">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-warning">Paquetes</p>
                        <h4 class="mt-2 text-xl font-bold text-on-primary">Configuración comercial</h4>
                    </div>
                    <p class="max-w-xl text-sm text-primary-200">
                        Asocia uno o varios paquetes. Puedes repetir el mismo paquete y el sistema lo sumará como
                        cantidad. Si falta alguno, usa la opción de alta rápida desde el selector.
                    </p>
                </div>

                <div class="space-y-5">
                    @for ($i = 0; $i < $countPackageInputs; $i++)
                        <div class="rounded-2xl border border-primary-700/60 bg-primary-800/60 p-5">
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                <div class="space-y-2">
                                    <label class="{{ $labelClass }}" for="package_id_{{ $i }}">Paquete
                                        {{ $i + 1 }}</label>
                                    <select class="{{ $fieldClass($errors->has('package_id') ? 'package_id' : 'package_id.'.$i) }} appearance-none"
                                        id="package_id_{{ $i }}"
                                        aria-invalid="{{ $errors->has('package_id') || $errors->has('package_id.'.$i) ? 'true' : 'false' }}"
                                        wire:model.live="package_id.{{ $i }}">
                                        <option value="">Seleccione un paquete</option>
                                        @foreach ($packages as $package)
                                            <option value="{{ $package->id }}">
                                                {{ $package->name }} - ${{ number_format($package->price, 2) }}
                                            </option>
                                        @endforeach
                                        <option value="-1">Agregar paquete</option>
                                    </select>
                                    @if ($i === $countPackageInputs - 1)
                                        <p class="text-xs text-primary-200">
                                            Usa la opción <span class="font-semibold text-secondary">Agregar
                                                paquete</span> si
                                            necesitas registrarlo sin salir del evento.
                                        </p>
                                    @endif
                                    @error('package_id.'.$i)
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                    @error('package_id')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-wrap gap-3 lg:justify-end">
                                    @if ($i === $countPackageInputs - 1)
                                        <button
                                            class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                                            type="button" wire:click="addPackageInput">
                                            <span class="material-symbols-outlined text-base">add</span>
                                            Agregar otro
                                        </button>
                                    @elseif ($countPackageInputs > 1)
                                        <button
                                            class="inline-flex items-center gap-2 rounded-xl bg-error/10 px-4 py-3 text-sm font-semibold text-error transition-colors hover:bg-error/20"
                                            type="button" wire:click="removePackageInput({{ $i }})">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                            Quitar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </section>
        @else
            <section class="{{ $sectionClass }}">
                <div class="{{ $sectionHeaderClass }}">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Composición
                            personalizada</p>
                        <h4 class="mt-2 text-xl font-bold text-on-primary">Constructor del evento</h4>
                    </div>
                    <p class="max-w-xl text-sm text-primary-200">
                        Agrega productos reales al evento sin dar de alta un paquete formal. La composición se guardará
                        solo dentro de esta contratación.
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-accent/30 bg-accent/10 p-5">
                        <p class="text-sm font-semibold text-accent">Flujo recomendado</p>
                        <p class="mt-2 text-sm leading-6 text-primary-100">
                            1. Busca productos por nombre.
                            2. Define una cantidad base o registra uno nuevo si todavía no existe.
                            3. Agrégalos al evento.
                            4. Captura el <span class="font-semibold">precio final</span> en la sección comercial.
                        </p>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_180px]">
                        <div class="space-y-2">
                            <label class="{{ $labelClass }}" for="customProductSearch">Buscar productos</label>
                            <input class="{{ $fieldClass('customProductSearch') }}" id="customProductSearch"
                                placeholder="Ej. cake, bomba, cruz del sur..." type="text"
                                wire:model.live.debounce.300ms="customProductSearch" aria-invalid="{{ $errors->has('customProductSearch') ? 'true' : 'false' }}">
                        </div>
                        <div class="space-y-2">
                            <label class="{{ $labelClass }}" for="customProductQuantity">Cantidad a agregar</label>
                            <input class="{{ $fieldClass('customProductQuantity') }}" id="customProductQuantity" min="1"
                                type="number" wire:model.live="customProductQuantity" aria-invalid="{{ $errors->has('customProductQuantity') ? 'true' : 'false' }}">
                        </div>
                    </div>

                    <div class="rounded-2xl border border-secondary/30 bg-secondary/10 p-5">
                        <div class="flex flex-col gap-3 border-b border-secondary/20 pb-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-secondary">Producto nuevo para este evento</p>
                                <p class="mt-2 text-sm text-primary-100">
                                    Si el material no existe en catálogo o no está dado de alta en inventario, puedes
                                    crearlo aquí. Al guardar el evento también quedará registrado en inventario.
                                </p>
                            </div>
                            <button
                                class="inline-flex items-center gap-2 rounded-xl bg-secondary px-4 py-3 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-95"
                                type="button" wire:click="addNewCustomProduct">
                                <span class="material-symbols-outlined text-base"
                                    style="font-variation-settings: 'FILL' 1;">playlist_add</span>
                                Crear y agregar
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="space-y-2 xl:col-span-2">
                                <label class="{{ $labelClass }}" for="newCustomProductName">Nombre <span class="text-red-400">*</span></label>
                                <input class="{{ $fieldClass('newCustomProductName') }}" id="newCustomProductName"
                                    placeholder="Ej. Volcán cascada oro" type="text"
                                    wire:model="newCustomProductName" aria-invalid="{{ $errors->has('newCustomProductName') ? 'true' : 'false' }}">
                                @error('newCustomProductName')
                                    <p class="{{ $errorClass }}">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="{{ $labelClass }}" for="newCustomProductRoleId">Tipo</label>
                                <select class="{{ $fieldClass('newCustomProductRoleId') }} appearance-none" id="newCustomProductRoleId"
                                    wire:model="newCustomProductRoleId" aria-invalid="{{ $errors->has('newCustomProductRoleId') ? 'true' : 'false' }}">
                                    <option value="1">Producto</option>
                                    <option value="2">Material</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="{{ $labelClass }}" for="newCustomProductUnit">Unidad <span class="text-red-400">*</span></label>
                                <input class="{{ $fieldClass('newCustomProductUnit') }}" id="newCustomProductUnit" placeholder="pz"
                                    type="text" wire:model="newCustomProductUnit" aria-invalid="{{ $errors->has('newCustomProductUnit') ? 'true' : 'false' }}">
                                @error('newCustomProductUnit')
                                    <p class="{{ $errorClass }}">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2 md:col-span-2 xl:col-span-4">
                                <label class="{{ $labelClass }}" for="newCustomProductDescription">Descripción</label>
                                <input class="{{ $fieldClass('newCustomProductDescription') }}" id="newCustomProductDescription"
                                    placeholder="Opcional. Si la dejas vacía, se usará el nombre como referencia."
                                    type="text" wire:model="newCustomProductDescription" aria-invalid="{{ $errors->has('newCustomProductDescription') ? 'true' : 'false' }}">
                            </div>

                            <div class="space-y-2">
                                <label class="{{ $labelClass }}" for="newCustomProductQuantity">Cantidad para el evento</label>
                                <input class="{{ $fieldClass('newCustomProductQuantity') }}" id="newCustomProductQuantity" min="1"
                                    type="number" wire:model.live="newCustomProductQuantity" aria-invalid="{{ $errors->has('newCustomProductQuantity') ? 'true' : 'false' }}">
                            </div>

                            <div class="space-y-2">
                                <label class="{{ $labelClass }}" for="newCustomProductStock">Stock inicial inventario</label>
                                <input class="{{ $fieldClass('newCustomProductStock') }}" id="newCustomProductStock" min="0"
                                    placeholder="Se ajusta al menos a la cantidad del evento" type="number"
                                    wire:model="newCustomProductStock" aria-invalid="{{ $errors->has('newCustomProductStock') ? 'true' : 'false' }}">
                            </div>

                            <div class="space-y-2">
                                <label class="{{ $labelClass }}" for="newCustomProductPrice">Costo inventario (opcional)</label>
                                <input class="{{ $fieldClass('newCustomProductPrice') }}" id="newCustomProductPrice" placeholder="$0.00"
                                    type="text" wire:model="newCustomProductPrice" aria-invalid="{{ $errors->has('newCustomProductPrice') ? 'true' : 'false' }}">
                            </div>

                            <div class="space-y-2">
                                <label class="{{ $labelClass }}" for="newCustomProductEventPrice">Precio al cliente (opcional)</label>
                                <input class="{{ $fieldClass('newCustomProductEventPrice') }}" id="newCustomProductEventPrice" placeholder="$0.00"
                                    type="text" wire:model="newCustomProductEventPrice" aria-invalid="{{ $errors->has('newCustomProductEventPrice') ? 'true' : 'false' }}">
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(320px,0.95fr)]">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-on-primary">Catálogo de productos</p>
                                    <p class="text-xs text-primary-200">Selecciona piezas reales para construir este
                                        evento.</p>
                                </div>
                                <span
                                    class="rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-xs font-semibold text-primary-100">
                                    {{ $customCatalogProducts->count() }} visibles
                                </span>
                            </div>

                            @if ($customCatalogProducts->isNotEmpty())
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach ($customCatalogProducts as $catalogProduct)
                                        <button
                                            class="group rounded-2xl border border-primary-700/60 bg-primary-800/60 p-4 text-left transition-all hover:border-accent/40 hover:bg-primary-900/60"
                                            type="button" wire:click="addCustomProduct({{ $catalogProduct->id }})">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-on-primary">
                                                        {{ $catalogProduct->name }}</p>
                                                    @php
                                                        $descriptor = collect([
                                                            $catalogProduct->caliber
                                                                ? $catalogProduct->caliber . '"'
                                                                : null,
                                                            $catalogProduct->shots
                                                                ? $catalogProduct->shots . ' tiros'
                                                                : null,
                                                            $catalogProduct->shape ?: null,
                                                            $catalogProduct->unit ?: null,
                                                        ])
                                                            ->filter()
                                                            ->implode(' · ');
                                                    @endphp
                                                    @if ($descriptor !== '')
                                                        <p class="mt-1 text-xs text-primary-200">{{ $descriptor }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <span
                                                    class="material-symbols-outlined text-primary-200 transition group-hover:text-accent">add_circle</span>
                                            </div>
                                            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                                <span
                                                    class="rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-primary-100">
                                                    Stock
                                                    {{ $catalogProduct->inventories->first()->pivot->quantity ?? 0 }}
                                                </span>
                                                @if (isset($customSelectedIndex[$catalogProduct->id]))
                                                    <span
                                                        class="rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-accent">
                                                        Agregado x{{ $customSelectedIndex[$catalogProduct->id] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl border border-primary-700/60 bg-primary-800/60 p-5">
                                    <p class="text-sm font-semibold text-on-primary">Sin resultados</p>
                                    <p class="mt-2 text-sm text-primary-200">No encontré materiales con ese filtro.
                                        Prueba otro nombre o borra la búsqueda.</p>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4 {{ $errors->has('customProducts') ? 'rounded-2xl border border-red-500 p-4' : '' }}">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-on-primary">Resumen del paquete personalizado
                                    </p>
                                    <p class="text-xs text-primary-200">Edita cantidades o elimina piezas antes de
                                        guardar.</p>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                    <span
                                        class="rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-accent">
                                        {{ $customSelectionItems->count() }} materiales
                                    </span>
                                    <span
                                        class="rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-1 text-primary-100">
                                        {{ $customSelectionItems->sum('quantity') }} piezas
                                    </span>
                                </div>
                            </div>

                            @error('customProducts')
                                <p class="{{ $errorClass }}">{{ $message }}</p>
                            @enderror

                            @if ($customSelectionItems->isNotEmpty())
                                <div class="space-y-3">
                                    @foreach ($customSelectionItems as $item)
                                        <article
                                            class="rounded-2xl border border-primary-700/60 bg-primary-800/60 p-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm font-semibold text-on-primary">
                                                        {{ $item['name'] }}</p>
                                                    @if ($item['descriptor'] !== '')
                                                        <p class="mt-1 text-xs text-primary-200">
                                                            {{ $item['descriptor'] }}</p>
                                                    @endif
                                                    @if ($item['is_new'])
                                                        <p class="mt-2 text-xs font-semibold text-secondary">
                                                            Se dará de alta como producto nuevo del inventario al guardar.
                                                        </p>
                                                    @elseif ($item['requires_inventory_registration'])
                                                        <p class="mt-2 text-xs font-semibold text-warning">
                                                            No existe en inventario. Se registrará automáticamente al guardar.
                                                        </p>
                                                    @endif
                                                    <p class="mt-2 text-xs font-semibold text-primary-100">Stock
                                                        actual: {{ $item['stock'] }}</p>
                                                    <p class="mt-1 text-xs text-primary-200">
                                                        El costo de inventario no se usará como precio de venta del evento.
                                                    </p>
                                                </div>
                                                <button
                                                    class="inline-flex items-center gap-2 rounded-xl bg-error/10 px-3 py-2 text-xs font-semibold text-error transition-colors hover:bg-error/20"
                                                    type="button"
                                                    wire:click="removeCustomProduct({{ $item['index'] }})">
                                                    <span class="material-symbols-outlined text-base">delete</span>
                                                    Quitar
                                                </button>
                                            </div>
                                            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                                <div class="space-y-2">
                                                    <label class="{{ $labelClass }}"
                                                        for="custom_product_qty_{{ $item['index'] }}">Cantidad</label>
                                                    <input class="{{ $inputClass }}"
                                                        id="custom_product_qty_{{ $item['index'] }}" min="1"
                                                        type="number"
                                                        wire:model.live="customProducts.{{ $item['index'] }}.quantity">
                                                </div>
                                                @if ($item['requires_inventory_registration'])
                                                    <div class="space-y-2">
                                                        <label class="{{ $labelClass }}"
                                                            for="custom_product_stock_{{ $item['index'] }}">Stock inventario</label>
                                                        <input class="{{ $inputClass }}"
                                                            id="custom_product_stock_{{ $item['index'] }}" min="0"
                                                            type="number"
                                                            wire:model.live="customProducts.{{ $item['index'] }}.inventory_quantity">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="{{ $labelClass }}"
                                                            for="custom_product_inventory_price_{{ $item['index'] }}">Costo inventario (opcional)</label>
                                                        <input class="{{ $inputClass }}"
                                                            id="custom_product_inventory_price_{{ $item['index'] }}"
                                                            placeholder="$0.00" type="text"
                                                            wire:model.live="customProducts.{{ $item['index'] }}.inventory_price">
                                                    </div>
                                                @else
                                                    <div class="rounded-2xl border border-primary-700/60 bg-primary-900/30 p-3 text-xs text-primary-200 md:col-span-1 xl:col-span-2">
                                                        Esta cantidad se guardará directamente en los materiales del evento y el stock reservado se calculará con la existencia actual.
                                                    </div>
                                                @endif
                                                <div class="space-y-2">
                                                    <label class="{{ $labelClass }}"
                                                        for="custom_product_price_{{ $item['index'] }}">Precio al cliente (opcional)</label>
                                                    <input class="{{ $inputClass }}"
                                                        id="custom_product_price_{{ $item['index'] }}"
                                                        placeholder="$0.00" type="text"
                                                        wire:model.live="customProducts.{{ $item['index'] }}.price">
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl border border-warning/30 bg-warning/10 p-5">
                                    <p class="text-sm font-semibold text-warning">Aún no hay materiales agregados</p>
                                    <p class="mt-2 text-sm text-primary-100">
                                        Usa el catálogo de la izquierda para ir agregando productos al evento antes de
                                        guardar.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="grid gap-6 2xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <div class="{{ $sectionClass }}">
                <div class="{{ $sectionHeaderClass }}">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Operación</p>
                        <h4 class="mt-2 text-xl font-bold text-on-primary">Equipo responsable</h4>
                    </div>
                    <a class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                        href="{{ route('employees.create') }}">
                        <span class="material-symbols-outlined text-base">badge</span>
                        Alta de empleado
                    </a>
                </div>

                <div class="space-y-5">
                    @for ($i = 0; $i < $countEmployeeInputs; $i++)
                        <div class="rounded-2xl border border-primary-700/60 bg-primary-800/60 p-5">
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                <div class="space-y-2">
                                    <label class="{{ $labelClass }}"
                                        for="employee_id_{{ $i }}">Encargado {{ $i + 1 }}</label>
                                    <select class="{{ $inputClass }} appearance-none"
                                        id="employee_id_{{ $i }}"
                                        wire:model="employee_id.{{ $i }}">
                                        <option value="">Seleccione un encargado</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <p class="{{ $errorClass }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-wrap gap-3 lg:justify-end">
                                    @if ($i === $countEmployeeInputs - 1)
                                        <button
                                            class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                                            type="button" wire:click="addEmployeeInput">
                                            <span class="material-symbols-outlined text-base">add</span>
                                            Agregar otro
                                        </button>
                                    @elseif ($countEmployeeInputs > 1)
                                        <button
                                            class="inline-flex items-center gap-2 rounded-xl bg-error/10 px-4 py-3 text-sm font-semibold text-error transition-colors hover:bg-error/20"
                                            type="button" wire:click="removeEmployeeInput({{ $i }})">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                            Quitar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="{{ $sectionClass }}">
                <div class="{{ $sectionHeaderClass }}">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-accent">Condiciones</p>
                        <h4 class="mt-2 text-xl font-bold text-on-primary">Ajustes comerciales</h4>
                    </div>
                    <p class="max-w-md text-sm text-primary-200">
                        Configura descuento, anticipos y notas que complementan el cierre de la contratación.
                    </p>
                </div>

                @if ($packageMode === 'custom')
                    <div class="mb-5 rounded-2xl border border-warning/30 bg-warning/10 p-4">
                        <p class="text-sm font-semibold text-warning">Precio final requerido</p>
                        <p class="mt-2 text-sm text-primary-100">
                            Como este evento no registrará un paquete formal, el campo <span
                                class="font-semibold">Precio final</span>
                            será la referencia para agenda, contrato y métricas.
                        </p>
                    </div>
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="{{ $labelClass }}" for="discount">Descuento</label>
                        <input class="{{ $fieldClass('discountString') }}" id="discount" placeholder="10% o $1,500"
                            type="text" wire:model="discountString" aria-invalid="{{ $errors->has('discountString') ? 'true' : 'false' }}">
                        @error('discount')
                            <p class="{{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="{{ $labelClass }}" for="deposit">Anticipo</label>
                        <input class="{{ $fieldClass('deposit') }}" id="deposit" placeholder="$0.00" type="text"
                            wire:model="deposit" aria-invalid="{{ $errors->has('deposit') ? 'true' : 'false' }}">
                        @error('deposit')
                            <p class="{{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="{{ $labelClass }}" for="viatic">Viáticos</label>
                        <input class="{{ $fieldClass('viatic') }}" id="viatic" placeholder="$0.00" type="text"
                            wire:model="viatic" aria-invalid="{{ $errors->has('viatic') ? 'true' : 'false' }}">
                        @error('viatic')
                            <p class="{{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="{{ $labelClass }}" for="price">Precio final @if ($packageMode === 'custom') <span class="text-red-400">*</span> @endif</label>
                        <input class="{{ $fieldClass('price') }}" id="price" placeholder="$0.00" type="text"
                            wire:model="price" aria-invalid="{{ $errors->has('price') ? 'true' : 'false' }}">
                        @error('price')
                            <p class="{{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($packageMode === 'custom')
                        <div class="space-y-2 md:col-span-2">
                            <label class="{{ $labelClass }}" for="contract_description">Descripción en contrato</label>
                            <textarea class="{{ $fieldClass('contract_description') }} min-h-28" id="contract_description"
                                placeholder="Opcional. Describe cómo debe presentarse este paquete personalizado ante el cliente en el contrato."
                                wire:model="contract_description" aria-invalid="{{ $errors->has('contract_description') ? 'true' : 'false' }}"></textarea>
                            <p class="text-xs text-primary-200">
                                Si la capturas, el contrato mostrará esta descripción comercial en lugar del desglose
                                técnico de materiales.
                            </p>
                            @error('contract_description')
                                <p class="{{ $errorClass }}">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="space-y-2 md:col-span-2">
                        <label class="{{ $labelClass }}" for="notes">Notas</label>
                        <textarea class="{{ $fieldClass('notes') }} min-h-28" id="notes"
                            placeholder="Observaciones, acuerdos especiales o requerimientos del cliente" wire:model="notes" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}"></textarea>
                        @error('notes')
                            <p class="{{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        @if ($packageMode === 'registered')
            <section class="{{ $sectionClass }}">
                <div class="{{ $sectionHeaderClass }}">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-warning">Materiales variables
                        </p>
                        <h4 class="mt-2 text-xl font-bold text-on-primary">Selección por paquete</h4>
                    </div>
                    <p class="max-w-xl text-sm text-primary-200">
                        Cuando un paquete requiera elegir materiales equivalentes, aquí podrás decidir qué producto se
                        usará.
                    </p>
                </div>

                @if (count($products))
                    <div class="space-y-4">
                        @foreach ($products as $indexProducts => $product)
                            @isset($product->pivot)
                                @for ($indexMaterial = 0; $indexMaterial < $product->pivot->quantity; $indexMaterial++)
                                    @php
                                        $selectionKey = $indexProducts . '_' . $indexMaterial;
                                    @endphp
                                    <article class="rounded-2xl border border-primary-700/60 bg-primary-800/60 p-5">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div>
                                                <p class="text-lg font-semibold text-on-primary">{{ $product->name }}</p>
                                                <p class="mt-1 text-sm text-primary-200">
                                                    Selecciona el material para la unidad {{ $indexMaterial + 1 }}.
                                                </p>
                                            </div>
                                            <span
                                                class="inline-flex items-center rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-warning">
                                                {{ $product->pivot->quantity }} requerido(s)
                                            </span>
                                        </div>

                                        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                            @foreach ($product->products as $material)
                                                <label
                                                    class="group block cursor-pointer rounded-2xl border border-primary-700/60 bg-primary-900/40 p-4 transition-all hover:border-accent/40 hover:bg-primary-900/70">
                                                    <input class="peer sr-only"
                                                        name="products-{{ $indexProducts }}-{{ $indexMaterial }}"
                                                        type="radio" value="{{ $material->id }}"
                                                        wire:key="product-option-{{ $selectionKey }}-{{ $material->id }}"
                                                        wire:model="radioSelected.{{ $selectionKey }}">
                                                    <span
                                                        class="block rounded-xl border border-transparent p-2 transition-all peer-checked:border-accent/40 peer-checked:bg-accent/10">
                                                        <span class="flex items-start justify-between gap-3">
                                                            <span>
                                                                <span class="block text-sm font-semibold text-on-primary">
                                                                    {{ $material->name }}
                                                                </span>
                                                                <span class="mt-1 block text-xs text-primary-200">
                                                                    Stock registrado:
                                                                    {{ $material->inventories->first()->pivot->quantity ?? 0 }}
                                                                </span>
                                                            </span>
                                                            <span
                                                                class="material-symbols-outlined text-primary-200 transition peer-checked:text-accent">radio_button_checked</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </article>
                                @endfor
                            @endisset
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-accent/30 bg-accent/10 p-5">
                        <p class="text-sm font-semibold text-accent">Sin variables adicionales</p>
                        <p class="mt-2 text-sm text-primary-100">
                            Los paquetes seleccionados no requieren elección manual de materiales en esta etapa.
                        </p>
                    </div>
                @endif
            </section>
        @endif

        @if (session()->has('message'))
            <div class="rounded-2xl border border-success/30 bg-success/10 p-4">
                <p class="text-sm font-semibold text-success">{{ session('message') }}</p>
            </div>
        @endif

        @if ($this->showAlert)
            <div class="rounded-2xl border border-warning/30 bg-warning/10 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-3">
                        <span class="material-symbols-outlined text-warning">warning</span>
                        <div>
                            <p class="text-sm font-semibold text-warning">Confirmación requerida</p>
                            <p class="mt-1 text-sm text-primary-100">
                                {{ $this->errorMessage ?: 'Se detectó una validación pendiente antes de guardar el evento.' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button
                            class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                            type="button" wire:click="closeAlert">
                            Revisar selección
                        </button>
                        <button
                            class="inline-flex items-center gap-2 rounded-xl bg-warning px-4 py-3 text-sm font-bold text-on-warning transition-colors hover:bg-warning-400"
                            type="button" wire:click="saveAndContinue">
                            Continuar de todos modos
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <x-modal id="past-event-date" title="Fecha anterior a hoy">
            <x-slot:body class="space-y-4">
                <div class="rounded-2xl border border-warning/30 bg-warning/10 p-4">
                    <p class="text-sm font-semibold text-warning">Revisión requerida</p>
                    <p class="mt-2 text-sm leading-6 text-primary-100">
                        La fecha del evento es anterior a hoy. Si continúas, el registro se guardará como evento histórico.
                    </p>
                </div>
                <p class="text-sm text-primary-200">
                    Confirma solo si realmente necesitas capturar un evento pasado o corregir historial operativo.
                </p>
            </x-slot:body>
            <x-slot:footer class="flex flex-wrap justify-end gap-3">
                <button
                    class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                    type="button" wire:click="closePastDateModal">
                    Revisar fecha
                </button>
                <button
                    class="inline-flex items-center gap-2 rounded-xl bg-warning px-4 py-3 text-sm font-bold text-on-warning transition-colors hover:bg-warning-400"
                    type="button" wire:click="confirmPastDateAndSave">
                    Continuar de todos modos
                </button>
            </x-slot:footer>
        </x-modal>

        <div
            class="flex flex-col gap-4 rounded-3xl border border-primary-700/60 bg-primary-800/80 p-5 backdrop-blur lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-on-primary">
                    {{ $isEditMode ? 'Actualiza el evento cuando termines los ajustes.' : 'Guarda el evento para enviarlo a la agenda operativa.' }}
                </p>
                <p class="mt-1 text-sm text-primary-200">El registro usará la configuración actual del backend y sus
                    relaciones.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600"
                    href="{{ route('events.index') }}">
                    <span class="material-symbols-outlined text-base">close</span>
                    Cancelar
                </a>
                <button @class([
                    'inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition-all',
                    'bg-secondary text-on-secondary shadow-lg shadow-secondary/20 hover:bg-secondary-600 active:scale-95' =>
                        $this->enableSave,
                    'cursor-not-allowed bg-primary-700 text-primary-200 opacity-60' => !$this->enableSave,
                ]) @disabled(!$this->enableSave) type="submit"
                    wire:loading.attr="disabled">
                    <span class="material-symbols-outlined text-base" wire:loading.remove
                        wire:target="save,saveAndContinue">
                        save
                    </span>
                    <span wire:loading.remove wire:target="save,saveAndContinue">
                        {{ $isEditMode ? 'Actualizar evento' : 'Guardar evento' }}
                    </span>
                    <span wire:loading wire:target="save,saveAndContinue">Guardando...</span>
                </button>
            </div>
        </div>
    </form>

    <x-modal id="new-package" title="Nuevo paquete">
        <x-slot:body class="space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Alta rápida</p>
                <p class="mt-2 text-sm text-primary-200">
                    Guarda el paquete y después podrás seleccionarlo dentro del mismo evento.
                </p>
            </div>
            <livewire:panel.settings.packages.package-form :isTabs="false" />
        </x-slot:body>
    </x-modal>

    <x-modal id="new-employee" title="Nuevo encargado">
        <x-slot:body class="space-y-4">
            <p class="text-sm text-primary-200">
                El alta de empleados se administra desde el módulo correspondiente.
            </p>
            <a class="inline-flex items-center gap-2 rounded-xl bg-secondary px-4 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600"
                href="{{ route('employees.create') }}">
                <span class="material-symbols-outlined text-base">badge</span>
                Ir a empleados
            </a>
        </x-slot:body>
    </x-modal>
</div>

@script
    <script>
        const toggleModalVisibility = (id, shouldOpen) => {
            if (!id) {
                return;
            }

            const modalElement = document.getElementById(id);

            if (!modalElement) {
                return;
            }

            modalElement.classList.toggle('hidden', !shouldOpen);
            modalElement.classList.toggle('flex', shouldOpen);
            modalElement.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
            document.body.classList.toggle('overflow-hidden', shouldOpen);
        };

        Livewire.on('closeModal', (data) => {
            toggleModalVisibility(data[0]?.id, false);
        });

        Livewire.on('openModal', (data) => {
            toggleModalVisibility(data[0]?.id, true);
        });

        window.addEventListener('app-modal-close', (event) => {
            toggleModalVisibility(event.detail?.id, false);
        });

        window.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            document.querySelectorAll('[role="dialog"]').forEach((modalElement) => {
                if (!modalElement.classList.contains('hidden')) {
                    toggleModalVisibility(modalElement.id, false);
                }
            });
        });
    </script>
@endscript
