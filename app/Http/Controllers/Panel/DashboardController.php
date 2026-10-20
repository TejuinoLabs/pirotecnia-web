<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $timezone = 'America/Mexico_City';
        $now = Carbon::now($timezone);
        [$startDate, $endDate] = $this->resolveDateRange($request, $now);
        [$previousStartDate, $previousEndDate] = $this->resolvePreviousRange($startDate, $endDate);

        $events = Event::with(['packages', 'typeEvent', 'employees.experienceLevel', 'products'])
            ->whereBetween('event_date', [$previousStartDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->orderBy('event_date')
            ->get();

        $inventory = Inventory::with('products')->first();

        $rangeEvents = $this->filterEventsByRange($events, $startDate, $endDate);
        $previousRangeEvents = $this->filterEventsByRange($events, $previousStartDate, $previousEndDate);
        $futureRangeEvents = $rangeEvents
            ->filter(fn(Event $event) => $this->eventDate($event)->gte($now->copy()->startOfDay()))
            ->values();

        $revenueInRange = $rangeEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));
        $previousRevenue = $previousRangeEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));

        $packagesInRange = $rangeEvents->sum(fn(Event $event) => $this->countPackagesForEvent($event));
        $previousPackages = $previousRangeEvents->sum(fn(Event $event) => $this->countPackagesForEvent($event));

        $rangeGranularity = $this->resolveSeriesGranularity($startDate, $endDate);
        $monthlyRevenueSeries = $this->buildRangeRevenueSeries($rangeEvents, $startDate, $endDate, $now, $rangeGranularity);
        $eventTypeSegments = $this->buildEventTypeSegments($rangeEvents);
        $upcomingEvents = $this->buildRangeEvents($rangeEvents, $now);
        $employeeCoverageRows = $this->buildEmployeeCoverage($rangeEvents, $now);
        $employeeCoverageCollection = collect($employeeCoverageRows);
        $employeeCoverageAssignments = (int) $employeeCoverageCollection->sum('eventCount');
        $coveredEmployeesCount = (int) $employeeCoverageCollection->count();
        $averageCoveragePerEmployee = $coveredEmployeesCount > 0
            ? $employeeCoverageAssignments / $coveredEmployeesCount
            : 0;
        $topCoverageEmployee = $employeeCoverageCollection->first();
        $eventsWithoutEmployeesCount = $rangeEvents
            ->filter(fn(Event $event) => $event->employees->isEmpty())
            ->count();

        $lowStockItems = $this->buildLowStockItems($inventory, $rangeEvents);
        $lowStockCount = count($lowStockItems);
        $criticalLowStockCount = collect($lowStockItems)->where('color', 'error')->count();

        $itemActive = 1;

        return view('panel.dashboard', [
            'itemActive' => $itemActive,
            'selectedStartDate' => $startDate->toDateString(),
            'selectedEndDate' => $endDate->toDateString(),
            'rangeLabel' => $this->buildRangeLabel($startDate, $endDate),
            'comparisonRangeLabel' => $this->buildRangeLabel($previousStartDate, $previousEndDate),
            'monthlyRevenue' => $this->formatMoney($revenueInRange),
            'monthlyRevenueIndicator' => $this->formatTrendLabel($revenueInRange, $previousRevenue, 'Nueva actividad'),
            'monthlyRevenueIndicatorColor' => $revenueInRange >= $previousRevenue ? 'text-secondary' : 'text-warning',
            'eventsInRangeCount' => (string) $rangeEvents->count(),
            'eventsInRangeIndicator' => $this->buildEventRangeIndicator($rangeEvents, $now),
            'lowStockCount' => (string) $lowStockCount,
            'lowStockIndicator' => $lowStockCount > 0 ? 'Comprometido en el rango' : 'Sin riesgo en el rango',
            'lowStockIndicatorColor' => $lowStockCount > 0 ? 'text-error' : 'text-secondary',
            'packagesInRange' => (string) $packagesInRange,
            'packagesInRangeIndicator' => $this->formatTrendLabel($packagesInRange, $previousPackages, 'Nueva actividad'),
            'packagesInRangeIndicatorColor' => $packagesInRange >= $previousPackages ? 'text-secondary' : 'text-warning',
            'monthlyRevenueSeries' => $monthlyRevenueSeries,
            'eventTypeSegments' => $eventTypeSegments,
            'upcomingEvents' => $upcomingEvents,
            'employeeCoverageRows' => $employeeCoverageRows,
            'coveredEmployeesCount' => $coveredEmployeesCount,
            'employeeCoverageAssignments' => (string) $employeeCoverageAssignments,
            'averageCoveragePerEmployee' => $this->formatQuantity($averageCoveragePerEmployee),
            'eventsWithoutEmployeesCount' => $eventsWithoutEmployeesCount,
            'topCoverageEmployeeName' => $topCoverageEmployee['name'] ?? 'Sin asignaciones',
            'topCoverageEmployeeCount' => isset($topCoverageEmployee['eventCount'])
                ? (string) $topCoverageEmployee['eventCount']
                : '0',
            'lowStockItems' => \App\Support\PanelPagination::collection(collect($lowStockItems), 'low_stock_page'),
            'criticalLowStockCount' => $criticalLowStockCount,
            'chartGranularityLabel' => $this->resolveGranularityLabel($rangeGranularity),
            'futureEventsInRangeCount' => $futureRangeEvents->count(),
        ]);
    }

    private function resolveDateRange(Request $request, Carbon $referenceDate): array
    {
        $timezone = 'America/Mexico_City';
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');

        if (! $startDateInput || ! $endDateInput) {
            return [
                $referenceDate->copy()->startOfMonth(),
                $referenceDate->copy()->endOfMonth(),
            ];
        }

        try {
            $startDate = Carbon::createFromFormat('Y-m-d', $startDateInput, $timezone)->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m-d', $endDateInput, $timezone)->endOfDay();
        } catch (\Throwable $exception) {
            return [
                $referenceDate->copy()->startOfMonth(),
                $referenceDate->copy()->endOfMonth(),
            ];
        }

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    private function resolvePreviousRange(Carbon $startDate, Carbon $endDate): array
    {
        $rangeDays = max($startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1, 1);
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        $previousStartDate = $startDate->copy()->subDays($rangeDays)->startOfDay();

        return [$previousStartDate, $previousEndDate];
    }

    private function filterEventsByRange(Collection $events, Carbon $startDate, Carbon $endDate): Collection
    {
        return $events
            ->filter(fn(Event $event) => $this->eventDate($event)->between($startDate, $endDate, true))
            ->values();
    }

    private function eventDate(Event $event): Carbon
    {
        return Carbon::parse($event->event_date, 'America/Mexico_City');
    }

    private function calculateEventSubtotal(Event $event): float
    {
        if ((float) $event->price > 0) {
            return (float) $event->price;
        }

        return $event->packages->sum(function ($package) {
            $quantity = (int) ($package->pivot->quantity ?? 1);
            $unitPrice = (float) (($package->pivot->price ?? 0) > 0 ? $package->pivot->price : $package->price);

            return $unitPrice * max($quantity, 1);
        });
    }

    private function calculateEventTotal(Event $event): float
    {
        $subtotal = $this->calculateEventSubtotal($event);
        $discount = (float) $event->discount;
        $discountAmount = $discount > 1 ? $discount : $subtotal * $discount;

        return max($subtotal - $discountAmount + (float) $event->travel_expenses, 0);
    }

    private function countPackagesForEvent(Event $event): int
    {
        if ($event->packages->isNotEmpty()) {
            return (int) $event->packages->sum(function ($package) {
                return max((int) ($package->pivot->quantity ?? 1), 1);
            });
        }

        return $event->products->isNotEmpty() ? 1 : 0;
    }

    private function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    private function formatTrendLabel(float|int $current, float|int $previous, string $fallback): string
    {
        if ($previous <= 0) {
            return $current > 0 ? $fallback : 'Sin movimiento';
        }

        $difference = (($current - $previous) / $previous) * 100;

        if (abs($difference) < 0.05) {
            return 'Sin cambios';
        }

        return ($difference > 0 ? '+' : '') . number_format($difference, 1) . '%';
    }

    private function resolveSeriesGranularity(Carbon $startDate, Carbon $endDate): string
    {
        $days = $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;
        $months = $startDate->copy()->startOfMonth()->diffInMonths($endDate->copy()->startOfMonth()) + 1;

        if ($days <= 31) {
            return 'day';
        }

        if ($days <= 180) {
            return 'week';
        }

        if ($months <= 18) {
            return 'month';
        }

        if ($months <= 48) {
            return 'quarter';
        }

        return 'year';
    }

    private function resolveGranularityLabel(string $granularity): string
    {
        return match ($granularity) {
            'day' => 'dia',
            'week' => 'semana',
            'quarter' => 'trimestre',
            'year' => 'ano',
            default => 'mes',
        };
    }

    private function buildRangeRevenueSeries(
        Collection $events,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $referenceDate,
        string $granularity
    ): array {
        $series = collect();

        if ($granularity === 'day') {
            $cursor = $startDate->copy()->startOfDay();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->endOfDay();
                $bucketEvents = $events->filter(fn(Event $event) => $this->eventDate($event)->between($bucketStart, $bucketEnd, true));
                $value = $bucketEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));

                $series->push([
                    'label' => $bucketStart->locale('es')->isoFormat('D MMM'),
                    'value' => $value,
                    'formatted' => $this->formatMoney($value),
                    'isCurrent' => $referenceDate->between($bucketStart, $bucketEnd, true),
                ]);

                $cursor->addDay();
            }
        }

        if ($granularity === 'week') {
            $cursor = $startDate->copy()->startOfDay();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->addDays(6)->endOfDay();

                if ($bucketEnd->gt($endDate)) {
                    $bucketEnd = $endDate->copy()->endOfDay();
                }

                $bucketEvents = $events->filter(fn(Event $event) => $this->eventDate($event)->between($bucketStart, $bucketEnd, true));
                $value = $bucketEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));

                $series->push([
                    'label' => $bucketStart->locale('es')->isoFormat('D MMM'),
                    'value' => $value,
                    'formatted' => $this->formatMoney($value),
                    'isCurrent' => $referenceDate->between($bucketStart, $bucketEnd, true),
                ]);

                $cursor = $bucketEnd->copy()->addDay()->startOfDay();
            }
        }

        if ($granularity === 'month') {
            $cursor = $startDate->copy()->startOfMonth();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfMonth();
                $bucketEnd = $cursor->copy()->endOfMonth();

                if ($bucketStart->lt($startDate)) {
                    $bucketStart = $startDate->copy()->startOfDay();
                }

                if ($bucketEnd->gt($endDate)) {
                    $bucketEnd = $endDate->copy()->endOfDay();
                }

                $bucketEvents = $events->filter(fn(Event $event) => $this->eventDate($event)->between($bucketStart, $bucketEnd, true));
                $value = $bucketEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));

                $series->push([
                    'label' => Str::upper($cursor->locale('es')->isoFormat('MMM YY')),
                    'value' => $value,
                    'formatted' => $this->formatMoney($value),
                    'isCurrent' => $referenceDate->between($bucketStart, $bucketEnd, true),
                ]);

                $cursor->addMonthNoOverflow()->startOfMonth();
            }
        }

        if ($granularity === 'quarter') {
            $cursor = $startDate->copy()->startOfQuarter();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfQuarter();
                $bucketEnd = $cursor->copy()->endOfQuarter();

                if ($bucketStart->lt($startDate)) {
                    $bucketStart = $startDate->copy()->startOfDay();
                }

                if ($bucketEnd->gt($endDate)) {
                    $bucketEnd = $endDate->copy()->endOfDay();
                }

                $bucketEvents = $events->filter(fn(Event $event) => $this->eventDate($event)->between($bucketStart, $bucketEnd, true));
                $value = $bucketEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));

                $series->push([
                    'label' => 'T' . $cursor->quarter . ' ' . $cursor->format('y'),
                    'value' => $value,
                    'formatted' => $this->formatMoney($value),
                    'isCurrent' => $referenceDate->between($bucketStart, $bucketEnd, true),
                ]);

                $cursor->addQuarter()->startOfQuarter();
            }
        }

        if ($granularity === 'year') {
            $cursor = $startDate->copy()->startOfYear();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfYear();
                $bucketEnd = $cursor->copy()->endOfYear();

                if ($bucketStart->lt($startDate)) {
                    $bucketStart = $startDate->copy()->startOfDay();
                }

                if ($bucketEnd->gt($endDate)) {
                    $bucketEnd = $endDate->copy()->endOfDay();
                }

                $bucketEvents = $events->filter(fn(Event $event) => $this->eventDate($event)->between($bucketStart, $bucketEnd, true));
                $value = $bucketEvents->sum(fn(Event $event) => $this->calculateEventTotal($event));

                $series->push([
                    'label' => $cursor->format('Y'),
                    'value' => $value,
                    'formatted' => $this->formatMoney($value),
                    'isCurrent' => $referenceDate->between($bucketStart, $bucketEnd, true),
                ]);

                $cursor->addYear()->startOfYear();
            }
        }

        return $series->all();
    }

    private function buildEventTypeSegments(Collection $events): array
    {
        $grouped = $events
            ->groupBy(fn(Event $event) => optional($event->typeEvent)->name ?: 'Sin tipo')
            ->map->count()
            ->sortDesc();

        if ($grouped->count() > 4) {
            $topSegments = $grouped->take(3);
            $topSegments->put('Otros', $grouped->slice(3)->sum());
            $grouped = $topSegments;
        }

        $colors = ['primary', 'accent', 'secondary', 'warning'];

        return $grouped
            ->values()
            ->map(function ($value, $index) use ($grouped, $colors) {
                return [
                    'label' => $grouped->keys()->values()->get($index),
                    'value' => $value,
                    'color' => $colors[$index % count($colors)],
                ];
            })
            ->all();
    }

    private function buildRangeEvents(Collection $rangeEvents, Carbon $referenceDate): array
    {
        return $rangeEvents
            ->filter(fn(Event $event) => $this->eventDate($event)->gte($referenceDate->copy()->startOfDay()))
            ->sortBy(fn(Event $event) => $this->eventDate($event)->timestamp)
            ->take(5)
            ->map(function (Event $event) use ($referenceDate) {
                $eventDate = $this->eventDate($event)->locale('es');

                if ($eventDate->lt($referenceDate->copy()->startOfDay())) {
                    $status = ['label' => 'Finalizado', 'color' => 'primary'];
                } elseif ($eventDate->isToday()) {
                    $status = ['label' => 'Hoy', 'color' => 'warning'];
                } elseif ($eventDate->between($referenceDate, $referenceDate->copy()->endOfWeek(), true)) {
                    $status = ['label' => 'Esta semana', 'color' => 'secondary'];
                } else {
                    $status = ['label' => 'Programado', 'color' => 'accent'];
                }

                return [
                    'month' => Str::upper($eventDate->isoFormat('MMM')),
                    'day' => $eventDate->isoFormat('D'),
                    'year' => $eventDate->isoFormat('YYYY'),
                    'client' => $event->client_name ?: 'Cliente sin nombre',
                    'type' => optional($event->typeEvent)->name ?: 'Sin tipo',
                    'status' => $status['label'],
                    'statusColor' => $status['color'],
                    'price' => $this->formatMoney($this->calculateEventTotal($event)),
                ];
            })
            ->all();
    }

    private function buildEmployeeCoverage(Collection $rangeEvents, Carbon $referenceDate): array
    {
        return $rangeEvents
            ->flatMap(function (Event $event) {
                return $event->employees->map(function ($employee) use ($event) {
                    return [
                        'employee' => $employee,
                        'event' => $event,
                    ];
                });
            })
            ->groupBy(fn(array $assignment) => $assignment['employee']->id)
            ->map(function (Collection $assignments) use ($referenceDate) {
                $employee = $assignments->first()['employee'];
                $events = $assignments
                    ->pluck('event')
                    ->unique('id')
                    ->sortBy(fn(Event $event) => $this->eventDate($event)->timestamp)
                    ->values();

                $completedCount = $events
                    ->filter(fn(Event $event) => $this->eventDate($event)->lt($referenceDate->copy()->startOfDay()))
                    ->count();

                $upcomingCount = $events
                    ->filter(fn(Event $event) => $this->eventDate($event)->gte($referenceDate->copy()->startOfDay()))
                    ->count();

                $nextEvent = $events->first(
                    fn(Event $event) => $this->eventDate($event)->gte($referenceDate->copy()->startOfDay())
                );

                $lastEvent = $events
                    ->filter(fn(Event $event) => $this->eventDate($event)->lt($referenceDate->copy()->startOfDay()))
                    ->last();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name ?: 'Empleado sin nombre',
                    'experienceLevel' => $employee->experienceLevel?->name ?: 'Sin nivel',
                    'eventCount' => $events->count(),
                    'completedCount' => $completedCount,
                    'upcomingCount' => $upcomingCount,
                    'referenceLabel' => $nextEvent
                        ? 'Proximo: ' . $this->eventDate($nextEvent)->locale('es')->isoFormat('D MMM')
                        : ($lastEvent
                            ? 'Ultimo: ' . $this->eventDate($lastEvent)->locale('es')->isoFormat('D MMM')
                            : 'Sin fecha de referencia'),
                ];
            })
            ->sortByDesc('eventCount')
            ->values()
            ->take(8)
            ->all();
    }

    private function buildLowStockItems(?Inventory $inventory, Collection $rangeEvents): array
    {
        if (! $inventory) {
            return [];
        }

        $reservedByProduct = [];

        $rangeEvents->each(function (Event $event) use (&$reservedByProduct) {
            $event->products->each(function ($product) use (&$reservedByProduct) {
                if ((bool) ($product->pivot->check_almacen ?? false)) {
                    return;
                }

                $reservedByProduct[$product->id] = ($reservedByProduct[$product->id] ?? 0) + (float) ($product->pivot->quantity ?? 0);
            });
        });

        return $inventory->products
            ->map(function ($product) use ($reservedByProduct) {
                $currentQuantity = (float) ($product->pivot->quantity ?? 0);
                $reservedInRange = (float) ($reservedByProduct[$product->id] ?? 0);
                $projectedAvailable = max($currentQuantity - $reservedInRange, 0);
                $isCritical = $projectedAvailable <= max(1, Inventory::MIN_STOCK / 2);

                return [
                    'icon' => $this->resolveInventoryIcon($product->unit),
                    'title' => $product->display_name,
                    'subtitle' => $product->unit ?: 'Existencias',
                    'value' => $this->formatQuantity($projectedAvailable),
                    'subValue' => 'Apartado: ' . $this->formatQuantity($reservedInRange),
                    'projected_available' => $projectedAvailable,
                    'color' => $isCritical ? 'error' : 'warning',
                ];
            })
            ->filter(fn(array $item) => $item['projected_available'] <= Inventory::MIN_STOCK)
            ->sortBy('projected_available')
            ->map(function (array $item) {
                unset($item['projected_available']);

                return $item;
            })
            ->values()
            ->all();
    }

    private function resolveInventoryIcon(?string $unit): string
    {
        $normalizedUnit = Str::lower((string) $unit);

        return match (true) {
            str_contains($normalizedUnit, 'lt'),
            str_contains($normalizedUnit, 'litro') => 'opacity',
            str_contains($normalizedUnit, 'kg'),
            str_contains($normalizedUnit, 'gram') => 'science',
            str_contains($normalizedUnit, 'paq'),
            str_contains($normalizedUnit, 'pack') => 'inventory_2',
            default => 'inventory_2',
        };
    }

    private function formatQuantity(float $quantity): string
    {
        if (fmod($quantity, 1.0) === 0.0) {
            return (string) (int) $quantity;
        }

        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }

    private function buildRangeLabel(Carbon $startDate, Carbon $endDate): string
    {
        $startLabel = $startDate->copy()->locale('es')->isoFormat('D MMM YYYY');
        $endLabel = $endDate->copy()->locale('es')->isoFormat('D MMM YYYY');

        return $startDate->isSameDay($endDate) ? $startLabel : $startLabel . ' - ' . $endLabel;
    }

    private function buildEventRangeIndicator(Collection $rangeEvents, Carbon $referenceDate): string
    {
        if ($rangeEvents->isEmpty()) {
            return 'Sin eventos en el rango';
        }

        $todayCount = $rangeEvents->filter(fn(Event $event) => $this->eventDate($event)->isSameDay($referenceDate))->count();
        $completedCount = $rangeEvents->filter(fn(Event $event) => $this->eventDate($event)->lt($referenceDate->copy()->startOfDay()))->count();
        $pendingCount = $rangeEvents->filter(fn(Event $event) => $this->eventDate($event)->gt($referenceDate->copy()->endOfDay()))->count();

        $parts = [];

        if ($todayCount > 0) {
            $parts[] = 'Hoy: ' . $todayCount;
        }

        if ($pendingCount > 0) {
            $parts[] = $pendingCount . ' pendientes';
        }

        if ($completedCount > 0) {
            $parts[] = $completedCount . ' finalizados';
        }

        return implode(' · ', array_slice($parts, 0, 2));
    }
}
