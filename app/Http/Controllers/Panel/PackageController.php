<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Support\PanelPagination;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    //

    public function index(Request $request)
    {
        $packages = Package::with(['materials', 'equipments', 'experienceLevel'])
            ->withCount('events')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $search = trim((string) $request->query('search', ''));
        $filteredPackages = $search === '' ? $packages : $packages->filter(function (Package $package) use ($search) {
            $values = [
                $package->name,
                $package->description,
                $package->experienceLevel?->name,
                'PKG-' . str_pad((string) $package->id, 4, '0', STR_PAD_LEFT),
            ];

            return Str::contains(Str::lower(implode(' ', $values)), Str::lower($search));
        });
        $listRows = PanelPagination::collection($filteredPackages->values());

        $parentItemActive = 8;
        $itemActive = 0;

        return view('panel.settings.packages.index', compact('packages', 'listRows', 'search', 'parentItemActive', 'itemActive'));
    }

    public function create()
    {
        $parentItemActive = 8;
        $itemActive = 0;
        $package = null;

        return view('panel.settings.packages.create', compact('parentItemActive', 'itemActive', 'package'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'price' => 'required',
        ]);

        $package = new Package;
        $package->name = $request->name;
        $package->description = $request->description;
        $amount = $request->price;
        $amount = preg_replace('/[^\d.]/', '', $amount);
        $amountDouble = (float) $amount;
        $package->price = $amountDouble;
        if ($request->duration) {
            $package->duration = $request->duration;
        }
        if ($request->video_url) {
            $package->video_url = $request->video_url;
        }
        $package->save();

        return redirect()->route('packages.show', ['id' => $package->id]);
    }

    public function edit($id)
    {
        $package = Package::findOrFail($id);
        $parentItemActive = 8;
        $itemActive = 0;

        return view('panel.settings.packages.create', compact('package', 'parentItemActive', 'itemActive'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'price' => 'required',
        ]);

        $package = Package::find($id);
        $package->name = $request->name;
        $package->description = $request->description;
        $package->price = $request->price;
        if ($request->duration) {
            $package->duration = $request->duration;
        }
        if ($request->video_url) {
            $package->video_url = $request->video_url;
        }
        $package->save();

        return redirect()->route('packages.show', ['id' => $package->id]);
    }

    public function destroy($id)
    {
        $package = Package::find($id);
        $package->delete();

        return redirect()->route('settings.packages.index');
    }

    public function show($id)
    {
        $package = Package::query()
            ->with([
                'materials' => fn ($query) => $query->orderBy('product_role_id')->orderBy('name'),
                'equipments' => fn ($query) => $query->orderBy('name'),
                'experienceLevel',
                'events' => fn ($query) => $query->with('typeEvent')->orderByDesc('event_date'),
            ])
            ->withCount(['events', 'materials', 'equipments'])
            ->findOrFail($id);

        $packageCode = 'PKG-' . str_pad((string) $package->id, 4, '0', STR_PAD_LEFT);
        $statusMeta = $this->buildStatusMeta($package);
        $packageItems = $this->buildPackageItems($package->materials);
        $equipmentRows = $this->buildEquipmentRows($package->equipments);
        $eventTypeBadges = $this->buildEventTypeBadges($package->events);
        $executiveSummary = $this->buildExecutiveSummary($package);
        $historyEntries = $this->buildHistoryEntries($package);
        $similarPackage = $this->findSimilarPackage($package);
        $packageMeta = [
            'formattedPrice' => $this->formatCurrency($package->price),
            'duration' => $package->duration ?: 'Sin duración definida',
            'description' => $package->description ?: 'Sin descripción comercial registrada.',
            'experienceName' => $package->experienceLevel?->name ?: 'Sin nivel asignado',
            'experienceDescription' => $package->experienceLevel?->description
                ?: 'Este paquete aún no tiene un nivel operativo ligado dentro del catálogo.',
        ];

        $parentItemActive = 8;
        $itemActive = 0;

        return view('panel.settings.packages.show', compact(
            'package',
            'packageCode',
            'statusMeta',
            'packageItems',
            'equipmentRows',
            'eventTypeBadges',
            'executiveSummary',
            'historyEntries',
            'similarPackage',
            'packageMeta',
            'parentItemActive',
            'itemActive'
        ));
    }

    private function buildStatusMeta(Package $package): array
    {
        if ((int) $package->materials_count === 0 && (int) $package->equipments_count === 0) {
            return [
                'label' => 'Incompleto',
                'tone' => 'warning',
            ];
        }

        if ((int) $package->events_count > 0) {
            return [
                'label' => 'Con historial',
                'tone' => 'secondary',
            ];
        }

        return [
            'label' => 'Disponible',
            'tone' => 'accent',
        ];
    }

    private function buildPackageItems(Collection $items): Collection
    {
        return $items
            ->sortBy(fn ($item) => sprintf('%s-%s', (string) $item->product_role_id, Str::lower($item->name)))
            ->values()
            ->map(function ($item) {
                $category = match ((int) $item->product_role_id) {
                    2 => ['label' => 'Material', 'tone' => 'accent', 'icon' => 'science'],
                    default => ['label' => 'Producto', 'tone' => 'secondary', 'icon' => 'inventory_2'],
                };

                $observations = collect([
                    $item->description ?: null,
                    $item->caliber ? 'Calibre ' . $item->caliber : null,
                    $item->shots ? $item->shots . ' tiros' : null,
                    $item->duration ? 'Duración ' . $item->duration : null,
                    $item->shape ?: null,
                ])->filter()->implode(' · ');

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $category['label'],
                    'tone' => $category['tone'],
                    'icon' => $category['icon'],
                    'quantity' => $this->formatQuantity($item->pivot?->quantity ?? 0),
                    'unit' => $item->unit ?: 'Pza',
                    'observations' => $observations !== ''
                        ? Str::limit($observations, 120)
                        : 'Sin observaciones registradas.',
                ];
            });
    }

    private function buildEquipmentRows(Collection $equipments): Collection
    {
        return $equipments
            ->values()
            ->map(function ($equipment) {
                return [
                    'id' => $equipment->id,
                    'name' => $equipment->name,
                    'description' => $equipment->description ?: 'Equipo operativo vinculado al paquete.',
                    'quantity' => $this->formatQuantity($equipment->pivot?->quantity ?? 0),
                    'unit' => $equipment->unit ?: 'Pza',
                    'icon' => $this->resolveEquipmentIcon($equipment->name, $equipment->description),
                    'tone' => $this->resolveEquipmentTone($equipment->name),
                ];
            });
    }

    private function buildEventTypeBadges(Collection $events): Collection
    {
        $tones = ['secondary', 'accent', 'warning', 'primary'];

        return $events
            ->map(fn ($event) => $event->typeEvent)
            ->filter()
            ->unique('id')
            ->values()
            ->map(function ($eventType, $index) use ($tones) {
                return [
                    'id' => $eventType->id,
                    'name' => $eventType->name,
                    'description' => $eventType->description,
                    'tone' => $tones[$index % count($tones)],
                    'icon' => $this->resolveEventTypeIcon($eventType->name),
                ];
            });
    }

    private function buildExecutiveSummary(Package $package): array
    {
        $usageTrend = $this->buildUsageTrend($package->events);
        $pastEvents = $package->events
            ->filter(fn ($event) => filled($event->event_date) && Carbon::parse($event->event_date)->lte(Carbon::today()))
            ->sortByDesc('event_date')
            ->values();

        $futureEvents = $package->events
            ->filter(fn ($event) => filled($event->event_date) && Carbon::parse($event->event_date)->gt(Carbon::today()))
            ->sortBy('event_date')
            ->values();

        $referenceEvent = $pastEvents->first() ?: $futureEvents->first();

        return [
            'usageCount' => number_format((int) $package->events_count),
            'usageTrendLabel' => $usageTrend['label'],
            'usageTrendTone' => $usageTrend['tone'],
            'usageTrendIcon' => $usageTrend['icon'],
            'activityLabel' => $pastEvents->isNotEmpty()
                ? 'Último uso'
                : ($futureEvents->isNotEmpty() ? 'Próximo uso' : 'Actividad'),
            'activityValue' => $referenceEvent
                ? $this->formatDate($referenceEvent->event_date)
                : 'Sin registros',
            'activityDetail' => $referenceEvent
                ? collect([$referenceEvent->client_name, $referenceEvent->event_address])
                    ->filter()
                    ->implode(', ')
                ?: 'Evento vinculado sin detalle adicional en la base actual.'
                : 'Este paquete todavía no ha sido programado en eventos.',
        ];
    }

    private function buildUsageTrend(Collection $events): array
    {
        $today = Carbon::today();
        $currentStart = $today->copy()->subDays(29);
        $previousStart = $today->copy()->subDays(59);
        $previousEnd = $today->copy()->subDays(30);

        $datedEvents = $events
            ->filter(fn ($event) => filled($event->event_date))
            ->values();

        $currentCount = $datedEvents
            ->filter(fn ($event) => Carbon::parse($event->event_date)->between($currentStart, $today))
            ->count();

        $previousCount = $datedEvents
            ->filter(fn ($event) => Carbon::parse($event->event_date)->between($previousStart, $previousEnd))
            ->count();

        if ($currentCount === 0 && $previousCount === 0) {
            return [
                'label' => 'Sin movimiento reciente',
                'tone' => 'primary',
                'icon' => 'timeline',
            ];
        }

        if ($previousCount === 0) {
            return [
                'label' => '+' . $currentCount . ' eventos en 30 dias',
                'tone' => 'accent',
                'icon' => 'trending_up',
            ];
        }

        $variation = (($currentCount - $previousCount) / $previousCount) * 100;
        $roundedVariation = (int) round($variation);

        return [
            'label' => ($roundedVariation >= 0 ? '+' : '') . $roundedVariation . '% vs periodo previo',
            'tone' => $roundedVariation >= 0 ? 'accent' : 'warning',
            'icon' => $roundedVariation >= 0 ? 'trending_up' : 'trending_down',
        ];
    }

    private function findSimilarPackage(Package $package): ?array
    {
        $baseQuery = Package::query()
            ->where('id', '!=', $package->id)
            ->withCount('events');

        $similarPackage = null;

        if ($package->experience_level_id) {
            $similarPackage = (clone $baseQuery)
                ->where('experience_level_id', $package->experience_level_id)
                ->orderByRaw('ABS(price - ?) asc', [(float) $package->price])
                ->orderByDesc('events_count')
                ->first();
        }

        if (! $similarPackage) {
            $similarPackage = (clone $baseQuery)
                ->orderByRaw('ABS(price - ?) asc', [(float) $package->price])
                ->orderByDesc('events_count')
                ->first();
        }

        if (! $similarPackage) {
            return null;
        }

        return [
            'id' => $similarPackage->id,
            'name' => $similarPackage->name,
            'url' => route('packages.show', $similarPackage->id),
            'hint' => $package->experience_level_id && $package->experience_level_id === $similarPackage->experience_level_id
                ? 'Comparte el mismo nivel operativo.'
                : 'Es el paquete mas cercano por precio actual.',
        ];
    }

    private function buildHistoryEntries(Package $package): Collection
    {
        $entries = collect([
            [
                'key' => 'current',
                'label' => 'Registro actual',
                'tone' => 'secondary',
                'priceLabel' => $this->formatCurrency($package->price),
                'dateLabel' => 'Actualizado ' . $this->formatDate($package->updated_at),
                'note' => $package->created_at && $package->updated_at && $package->created_at->ne($package->updated_at)
                    ? 'Ultima actualizacion registrada en el catalogo.'
                    : 'La base actual no guarda versiones historicas adicionales para este paquete.',
            ],
        ]);

        if ($package->created_at && $package->updated_at && $package->created_at->ne($package->updated_at)) {
            $entries->push([
                'key' => 'created',
                'label' => 'Alta inicial',
                'tone' => 'primary',
                'priceLabel' => 'Sin snapshot',
                'dateLabel' => 'Creado ' . $this->formatDate($package->created_at),
                'note' => 'Existe fecha de alta, pero no un historial versionado de precio en este punto del tiempo.',
            ]);
        }

        return $entries;
    }

    private function resolveEquipmentIcon(?string $name, ?string $description): string
    {
        $haystack = Str::lower(trim(($name ?: '') . ' ' . ($description ?: '')));

        return match (true) {
            Str::contains($haystack, ['extintor', 'fire']) => 'fire_extinguisher',
            Str::contains($haystack, ['consola', 'control', 'router', 'ignicion']) => 'router',
            Str::contains($haystack, ['base', 'soporte', 'estructura']) => 'view_stream',
            default => 'construction',
        };
    }

    private function resolveEquipmentTone(?string $name): string
    {
        $value = Str::lower($name ?: '');

        return match (true) {
            Str::contains($value, ['extintor', 'seguridad']) => 'warning',
            Str::contains($value, ['consola', 'control']) => 'primary',
            default => 'accent',
        };
    }

    private function resolveEventTypeIcon(?string $name): string
    {
        $value = Str::lower($name ?: '');

        return match (true) {
            Str::contains($value, ['boda', 'novio', 'novia']) => 'celebration',
            Str::contains($value, ['patron', 'relig', 'igles']) => 'church',
            Str::contains($value, ['masivo', 'festival', 'feria']) => 'stadium',
            default => 'event',
        };
    }

    private function formatCurrency(float|int|string|null $amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }

    private function formatQuantity(float|int|string|null $quantity): string
    {
        $value = (float) $quantity;

        if (fmod($value, 1.0) === 0.0) {
            return number_format($value, 0);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }

    private function formatDate($date): string
    {
        if (! $date) {
            return 'Sin fecha';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }
}
