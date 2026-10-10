@extends('templates.adminlte')

@section('content-header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Empleados</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
                        <li class="breadcrumb-item active">Empleados</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('main-content')
    @php
        $normalizeAmount = static function ($value) {
            $normalized = preg_replace('/[^0-9\-\.,]/', '', trim((string) $value));

            if ($normalized === null || $normalized === '' || $normalized === '-') {
                return 0.0;
            }

            return (float) str_replace(',', '', $normalized);
        };

        $totalEmployees = $employees->count();
        $employeesWithPhone = $employees->filter(fn($employee) => filled(trim((string) $employee->phone)))->count();
        $employeesWithCompleteContact = $employees->filter(
            fn($employee) => filled(trim((string) $employee->email)) && filled(trim((string) $employee->phone))
        )->count();
        $experienceLevelsCovered = $employees->pluck('experienceLevel.name')->filter()->unique()->count();
        $salarySamples = $employees
            ->map(fn($employee) => $normalizeAmount($employee->salary))
            ->filter(fn($salary) => $salary > 0);
        $averageSalary = $salarySamples->isNotEmpty() ? $salarySamples->avg() : 0;
        $phoneCoverage = $totalEmployees > 0 ? round(($employeesWithPhone / $totalEmployees) * 100) : 0;
    @endphp

    <div class="mx-auto mt-16 w-full max-w-[1600px] space-y-8 px-4 py-6 sm:p-8">
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.8fr)]">
            <div class="relative overflow-hidden rounded-3xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
                <div class="absolute -right-12 -top-12 h-44 w-44 rounded-full bg-secondary/10 blur-3xl"></div>
                <div class="absolute bottom-0 right-8 h-28 w-28 rounded-full bg-accent/10 blur-3xl"></div>
                <div class="relative">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-secondary">Capital Humano</p>
                    <h2 class="mt-4 text-4xl font-bold tracking-tight text-on-primary">Equipo operativo</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-primary-200">
                        Administra la plantilla de Pirotecnia San Rafael desde una vista alineada al branding del panel,
                        con foco en identidad, contacto y control administrativo del personal.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('employees.create') }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:bg-secondary-600 active:scale-95">
                            <span class="material-symbols-outlined"
                                style="font-variation-settings: 'FILL' 1;">person_add</span>
                            Nuevo empleado
                        </a>
                        <a href="#employee-directory"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-5 py-3 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-600">
                            <span class="material-symbols-outlined">badge</span>
                            Ver directorio
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-gradient-to-br from-primary to-primary-700 p-8 shadow-2xl shadow-primary-900/30">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-100">Resumen rapido</p>
                        <p class="mt-3 text-3xl font-bold text-on-primary">{{ $totalEmployees }}</p>
                        <p class="mt-2 text-sm text-primary-100">colaboradores registrados en la plantilla actual.</p>
                    </div>
                    <span class="material-symbols-outlined text-4xl text-secondary">groups</span>
                </div>
                <div class="mt-8 space-y-4">
                    <div class="rounded-2xl bg-on-primary/10 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Cobertura de
                            contacto</p>
                        <p class="mt-2 text-lg font-semibold text-on-primary">{{ $phoneCoverage }}% con telefono
                            registrado</p>
                    </div>
                    <div class="rounded-2xl bg-on-primary/10 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-primary-100">Experiencia activa
                        </p>
                        <p class="mt-2 text-lg font-semibold text-on-primary">{{ $experienceLevelsCovered }} niveles
                            cargados</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl bg-primary-800 p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Plantilla total</p>
                <p class="mt-4 text-3xl font-bold text-on-primary">{{ $totalEmployees }}</p>
                <p class="mt-2 text-sm text-primary-200">personal operativo y administrativo disponible.</p>
            </article>
            <article class="rounded-2xl bg-primary-800 p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Con telefono</p>
                <p class="mt-4 text-3xl font-bold text-on-primary">{{ $employeesWithPhone }}</p>
                <p class="mt-2 text-sm text-primary-200">contactos listos para seguimiento rapido.</p>
            </article>
            <article class="rounded-2xl bg-primary-800 p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Niveles cubiertos</p>
                <p class="mt-4 text-3xl font-bold text-on-primary">{{ $experienceLevelsCovered }}</p>
                <p class="mt-2 text-sm text-primary-200">rangos de experiencia registrados en el sistema.</p>
            </article>
            <article class="rounded-2xl bg-primary-800 p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-200">Salario promedio</p>
                <p class="mt-4 text-3xl font-bold text-on-primary">
                    {{ $averageSalary > 0 ? '$' . number_format($averageSalary, 2) : 'Sin dato' }}
                </p>
                <p class="mt-2 text-sm text-primary-200">calculado con empleados que tienen monto capturado.</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-3xl bg-primary-800 shadow-2xl shadow-primary-900/20"
            id="employee-directory">
            <div
                class="flex flex-col gap-4 border-b border-primary-700/60 px-8 py-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-secondary">Directorio interno</p>
                    <h3 class="mt-2 text-2xl font-bold text-on-primary">Colaboradores registrados</h3>
                    <p class="mt-1 text-sm text-primary-200">Vista alineada al branding del panel para consultar y gestionar
                        empleados.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                    <span class="rounded-full border border-primary-600/60 bg-primary-700/70 px-3 py-2 text-primary-100">
                        {{ $totalEmployees }} en total
                    </span>
                    <span class="rounded-full border border-accent/30 bg-accent/10 px-3 py-2 text-accent">
                        {{ $experienceLevelsCovered }} niveles de experiencia
                    </span>
                    <span class="rounded-full border border-secondary/30 bg-secondary/10 px-3 py-2 text-secondary">
                        {{ $employeesWithCompleteContact }} contactos completos
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="responsive-stack-table responsive-stack-table-dark min-w-full text-left">
                    <thead>
                        <tr class="border-b border-primary-700/60 bg-primary-700/40">
                            <th class="px-8 py-5 text-xs font-bold uppercase tracking-[0.24em] text-primary-200">Empleado
                            </th>
                            <th class="px-8 py-5 text-xs font-bold uppercase tracking-[0.24em] text-primary-200">Contacto
                            </th>
                            <th class="px-8 py-5 text-xs font-bold uppercase tracking-[0.24em] text-primary-200">Nivel</th>
                            <th class="px-8 py-5 text-xs font-bold uppercase tracking-[0.24em] text-primary-200">Direccion
                            </th>
                            <th class="px-8 py-5 text-xs font-bold uppercase tracking-[0.24em] text-primary-200 text-right">
                                Salario</th>
                            <th
                                class="px-8 py-5 text-xs font-bold uppercase tracking-[0.24em] text-primary-200 text-center">
                                Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-700/60">
                        @php
                            $listRows = \App\Support\PanelPagination::collection($employees);
                        @endphp
                        @forelse ($listRows as $employee)
                            @php
                                $experienceLevel = optional($employee->experienceLevel)->name;
                                $initial = filled($employee->name)
                                    ? mb_strtoupper(mb_substr(trim($employee->name), 0, 1))
                                    : '?';
                            @endphp
                            <tr class="transition-colors hover:bg-primary-700/40">
                                <td class="px-8 py-6" data-label="Empleado">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/15 text-lg font-bold text-secondary">
                                            {{ $initial }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-on-primary">{{ $employee->name }}</p>
                                            <p class="text-xs uppercase tracking-[0.18em] text-primary-200">Empleado
                                                #{{ $employee->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6" data-label="Contacto">
                                    <div class="space-y-1 text-sm">
                                        <p class="text-on-primary">{{ $employee->email }}</p>
                                        <p class="text-primary-200">{{ $employee->phone ?: 'Sin telefono registrado' }}</p>
                                    </div>
                                </td>
                                <td class="px-8 py-6" data-label="Nivel">
                                    @if ($experienceLevel)
                                        <span
                                            class="inline-flex rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-accent">
                                            {{ $experienceLevel }}
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex rounded-full border border-warning/30 bg-warning/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-warning">
                                            Sin nivel
                                        </span>
                                    @endif
                                </td>
                                <td class="px-8 py-6" data-label="Dirección">
                                    <p class="max-w-xs text-sm text-primary-100">
                                        {{ $employee->address ?: 'Sin direccion registrada' }}
                                    </p>
                                </td>
                                <td class="px-8 py-6 text-right" data-label="Salario">
                                    <p class="font-semibold text-on-primary">
                                        {{ (float) $employee->salary > 0 ? '$' . number_format((float) $employee->salary, 2) : 'Sin capturar' }}
                                    </p>
                                </td>
                                <td class="px-8 py-6" data-label="Acciones">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('employees.show', $employee->id) }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-primary-200 transition-all hover:bg-primary/10 hover:text-primary"
                                            title="Ver empleado">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </a>
                                        <a href="{{ route('employees.edit', $employee->id) }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-primary-200 transition-all hover:bg-secondary/10 hover:text-secondary"
                                            title="Editar empleado">
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                        <form action="{{ route('employees.destroy', $employee->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center justify-center rounded-lg p-2 text-primary-200 transition-all hover:bg-error/10 hover:text-error"
                                                onclick="return confirm('¿Deseas eliminar este empleado?')"
                                                title="Eliminar empleado">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-8 py-16 text-center">
                                    <div class="mx-auto flex max-w-md flex-col items-center">
                                        <div
                                            class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-700 text-primary-100">
                                            <span class="material-symbols-outlined text-3xl">badge</span>
                                        </div>
                                        <h4 class="mt-5 text-xl font-bold text-on-primary">Aun no hay empleados registrados
                                        </h4>
                                        <p class="mt-2 text-sm leading-6 text-primary-200">
                                            Empieza la plantilla cargando el primer colaborador y manteniendo toda la
                                            operacion alineada con el panel administrativo.
                                        </p>
                                        <a href="{{ route('employees.create') }}"
                                            class="mt-6 inline-flex items-center gap-2 rounded-xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary transition-colors hover:bg-secondary-600">
                                            <span class="material-symbols-outlined"
                                                style="font-variation-settings: 'FILL' 1;">person_add</span>
                                            Registrar primer empleado
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-primary-700/60 px-8 py-5 text-sm text-primary-200 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    Mostrando <span class="font-semibold text-on-primary">{{ ($listRows ?? \App\Support\PanelPagination::collection($employees))->count() }} de {{ $totalEmployees }}</span>
                    colaboradores en esta vista.
                </p>
                <a href="{{ route('employees.create') }}"
                    class="inline-flex items-center gap-2 font-semibold text-secondary transition-colors hover:text-secondary-300">
                    <span class="material-symbols-outlined text-base">add_circle</span>
                    Agregar colaborador
                </a>
            </div>
        </section>
    </div>
    <x-panel.pagination :paginator="$listRows ?? \App\Support\PanelPagination::collection($employees)" />
@endsection
