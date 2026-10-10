@extends('templates.adminlte')

@section('main-content')
<div class="mx-auto mt-16 w-full max-w-[1680px] space-y-8 px-4 py-6 sm:p-8">
    <header>
        <h1 class="text-4xl font-extrabold text-on-primary">Configuración</h1>
        <p class="mt-3 text-lg text-primary-200">Administra los catálogos y parámetros del sistema.</p>
    </header>
    <section class="grid gap-6 md:grid-cols-2 lg:grid-cols-3" aria-label="Catálogos de configuración">
        @foreach ($modules as $module)
        <article class="flex flex-col rounded-2xl border border-primary-700 bg-primary-800 p-8">
            <div class="flex items-center justify-between">
                <span class="material-symbols-outlined text-3xl text-secondary">{{ $module['icon'] }}</span>
                <span class="rounded-full bg-primary-700 px-3 py-1 text-sm text-primary-200">{{ $module['count'] }} registros</span>
            </div>
            <h2 class="mt-6 text-xl font-bold">{{ $module['title'] }}</h2>
            <p class="mb-6 mt-3 flex-1 text-sm leading-relaxed text-primary-200">{{ $module['description'] }}</p>
            <a href="{{ route($module['route']) }}" class="rounded-xl bg-primary-700 px-4 py-3 text-center font-semibold hover:bg-primary-600" aria-label="Administrar {{ $module['title'] }}">Administrar</a>
        </article>
        @endforeach
    </section>
</div>
@endsection
