@props(['stats'])
<section aria-label="Indicadores del catálogo" class="grid gap-6 sm:grid-cols-2 {{ count($stats) > 3 ? 'xl:grid-cols-4' : 'lg:grid-cols-3' }}" data-settings-stats>
    @foreach ($stats as $stat)
        <article class="group relative overflow-hidden rounded-xl bg-primary-800 p-8 shadow-2xl shadow-primary-900/20">
            <span class="material-symbols-outlined absolute right-5 top-5 text-5xl text-primary-200 opacity-10" aria-hidden="true">{{ $stat['icon'] }}</span>
            <p class="mb-3 text-xs font-bold uppercase tracking-widest text-primary-200">{{ $stat['label'] }}</p>
            <p class="font-manrope text-5xl font-extrabold {{ $stat['tone'] ?? 'text-on-primary' }}">{{ number_format($stat['value']) }}</p>
            <p class="mt-4 flex items-center gap-2 text-xs font-semibold text-primary-200"><span class="material-symbols-outlined text-sm" aria-hidden="true">{{ $stat['icon'] }}</span>{{ $stat['hint'] }}</p>
        </article>
    @endforeach
</section>
