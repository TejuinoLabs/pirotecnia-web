<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim(preg_replace('/\s+/', ' ', (string) $request->query('q', '')) ?? '');
        $isNumericQuery = ctype_digit($query);
        $canSearch = $query !== '' && ($isNumericQuery || mb_strlen($query) >= 2);

        $resultGroups = collect([
            [
                'key' => 'events',
                'label' => 'Eventos',
                'description' => 'Clientes, fechas, paquetes y direcciones del show.',
                'icon' => 'event_upcoming',
                'tone' => 'secondary',
                'items' => $canSearch ? $this->searchEvents($query, $isNumericQuery) : collect(),
            ],
            [
                'key' => 'employees',
                'label' => 'Empleados',
                'description' => 'Personal operativo y administrativo registrado.',
                'icon' => 'badge',
                'tone' => 'accent',
                'items' => $canSearch ? $this->searchEmployees($query, $isNumericQuery) : collect(),
            ],
            [
                'key' => 'users',
                'label' => 'Usuarios',
                'description' => 'Accesos del panel y cuentas internas.',
                'icon' => 'shield_person',
                'tone' => 'warning',
                'items' => $canSearch ? $this->searchUsers($query, $isNumericQuery) : collect(),
            ],
            [
                'key' => 'products',
                'label' => 'Productos',
                'description' => 'Catalogo de pirotecnia y materiales.',
                'icon' => 'inventory_2',
                'tone' => 'secondary',
                'items' => $canSearch ? $this->searchProducts($query, $isNumericQuery) : collect(),
            ],
            [
                'key' => 'packages',
                'label' => 'Paquetes',
                'description' => 'Paquetes establecidos configurados en el sistema.',
                'icon' => 'package_2',
                'tone' => 'accent',
                'items' => $canSearch ? $this->searchPackages($query, $isNumericQuery) : collect(),
            ],
            [
                'key' => 'sales',
                'label' => 'Ventas',
                'description' => 'Registros comerciales y clientes atendidos.',
                'icon' => 'payments',
                'tone' => 'warning',
                'items' => $canSearch ? $this->searchSales($query, $isNumericQuery) : collect(),
            ],
        ]);

        $totalResults = $resultGroups->sum(fn (array $group) => $group['items'] instanceof LengthAwarePaginator ? $group['items']->total() : $group['items']->count());
        $activeGroups = $resultGroups->filter(fn (array $group) => $group['items']->isNotEmpty())->values();
        $searchNotice = $query === ''
            ? 'Escribe un nombre, telefono, folio, direccion o cliente para buscar en todo el panel.'
            : ($canSearch ? null : 'Usa al menos 2 caracteres o captura un folio numerico exacto.');

        return view('panel.search.index', [
            'query' => $query,
            'resultGroups' => $resultGroups,
            'activeGroups' => $activeGroups,
            'totalResults' => $totalResults,
            'searchNotice' => $searchNotice,
            'canSearch' => $canSearch,
        ]);
    }

    private function searchEvents(string $query, bool $isNumericQuery): LengthAwarePaginator
    {
        $like = '%'.$query.'%';

        return Event::query()
            ->with([
                'employees:id,name',
                'packages:id,name',
                'typeEvent:id,name',
            ])
            ->where(function ($search) use ($like, $query, $isNumericQuery) {
                $search->where('client_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('event_address', 'like', $like)
                    ->orWhere('client_address', 'like', $like)
                    ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('name', 'like', $like))
                    ->orWhereHas('employees', fn ($employeeQuery) => $employeeQuery->where('name', 'like', $like))
                    ->orWhereHas('typeEvent', fn ($typeQuery) => $typeQuery->where('name', 'like', $like));

                if ($isNumericQuery) {
                    $search->orWhereKey((int) $query);
                }
            })
            ->orderBy('event_date')
            ->paginate(15, ['*'], 'events_page')->withQueryString()
            ->through(function (Event $event) {
                $eventDate = $event->event_date
                    ? Carbon::parse($event->event_date, 'America/Mexico_City')->locale('es')->isoFormat('D MMM YYYY, HH:mm')
                    : 'Fecha sin definir';
                $packages = $event->packages->pluck('name')->filter()->implode(', ');
                $responsible = $event->employees->first()?->name;

                return [
                    'title' => $event->client_name ?: 'Cliente sin nombre',
                    'subtitle' => $event->event_address ?: ($event->client_address ?: 'Direccion sin registrar'),
                    'meta' => collect([
                        'Folio #'.$event->id,
                        $eventDate,
                        $event->typeEvent?->name,
                        $packages !== '' ? $packages : 'Paquete personalizado',
                        $responsible,
                    ])->filter()->implode(' · '),
                    'url' => route('events.show', $event->id),
                ];
            });
    }

    private function searchEmployees(string $query, bool $isNumericQuery): LengthAwarePaginator
    {
        $like = '%'.$query.'%';

        return Employee::query()
            ->with('experienceLevel:id,name')
            ->where(function ($search) use ($like, $query, $isNumericQuery) {
                $search->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhereHas('experienceLevel', fn ($levelQuery) => $levelQuery->where('name', 'like', $like));

                if ($isNumericQuery) {
                    $search->orWhereKey((int) $query);
                }
            })
            ->orderBy('name')
            ->paginate(15, ['*'], 'employees_page')->withQueryString()
            ->through(function (Employee $employee) {
                return [
                    'title' => $employee->name ?: 'Empleado sin nombre',
                    'subtitle' => $employee->email ?: 'Correo sin registrar',
                    'meta' => collect([
                        'Exp. '.($employee->experienceLevel?->name ?? 'Sin nivel'),
                        $employee->phone,
                        $employee->address,
                    ])->filter()->implode(' · '),
                    'url' => route('employees.show', $employee->id),
                ];
            });
    }

    private function searchUsers(string $query, bool $isNumericQuery): LengthAwarePaginator
    {
        $like = '%'.$query.'%';

        return User::query()
            ->where(function ($search) use ($like, $query, $isNumericQuery) {
                $search->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);

                if ($isNumericQuery) {
                    $search->orWhereKey((int) $query);
                }
            })
            ->orderBy('name')
            ->paginate(15, ['*'], 'users_page')->withQueryString()
            ->through(function (User $user) {
                return [
                    'title' => $user->name,
                    'subtitle' => $user->email,
                    'meta' => collect([
                        'Usuario #'.$user->id,
                        $user->phone,
                    ])->filter()->implode(' · '),
                    'url' => route('users.show', $user->id),
                ];
            });
    }

    private function searchProducts(string $query, bool $isNumericQuery): LengthAwarePaginator
    {
        $like = '%'.$query.'%';

        return Product::query()
            ->where('product_role_id', '!=', 3)
            ->where(function ($search) use ($like, $query, $isNumericQuery) {
                $search->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('unit', 'like', $like)
                    ->orWhere('duration', 'like', $like)
                    ->orWhere('shots', 'like', $like)
                    ->orWhere('caliber', 'like', $like);

                if ($isNumericQuery) {
                    $search->orWhereKey((int) $query);
                }
            })
            ->orderBy('name')
            ->paginate(15, ['*'], 'products_page')->withQueryString()
            ->through(function (Product $product) {
                return [
                    'title' => $product->name,
                    'subtitle' => Str::limit($product->description ?: 'Sin descripcion', 110),
                    'meta' => collect([
                        'Producto #'.$product->id,
                        $product->unit,
                        $product->duration ? $product->duration.' seg' : null,
                        $product->shots ? $product->shots.' disparos' : null,
                        $product->caliber,
                    ])->filter()->implode(' · '),
                    'url' => route('products.show', $product->id),
                ];
            });
    }

    private function searchPackages(string $query, bool $isNumericQuery): LengthAwarePaginator
    {
        $like = '%'.$query.'%';

        return Package::query()
            ->with('experienceLevel:id,name')
            ->where(function ($search) use ($like, $query, $isNumericQuery) {
                $search->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('experienceLevel', fn ($levelQuery) => $levelQuery->where('name', 'like', $like))
                    ->orWhereHas('products', fn ($productQuery) => $productQuery->where('name', 'like', $like));

                if ($isNumericQuery) {
                    $search->orWhereKey((int) $query);
                }
            })
            ->orderBy('name')
            ->paginate(15, ['*'], 'packages_page')->withQueryString()
            ->through(function (Package $package) {
                return [
                    'title' => $package->name,
                    'subtitle' => Str::limit($package->description ?: 'Paquete sin descripcion', 110),
                    'meta' => collect([
                        'Paquete #'.$package->id,
                        '$'.number_format((float) $package->price, 2),
                        $package->duration ? $package->duration.' seg' : null,
                        $package->experienceLevel?->name,
                    ])->filter()->implode(' · '),
                    'url' => route('packages.show', $package->id),
                ];
            });
    }

    private function searchSales(string $query, bool $isNumericQuery): LengthAwarePaginator
    {
        $like = '%'.$query.'%';

        return Sale::query()
            ->where(function ($search) use ($like, $query, $isNumericQuery) {
                $search->where('client_name', 'like', $like)
                    ->orWhere('client_phone', 'like', $like)
                    ->orWhere('client_address', 'like', $like);

                if ($isNumericQuery) {
                    $search->orWhereKey((int) $query);
                }
            })
            ->latest()
            ->paginate(15, ['*'], 'sales_page')->withQueryString()
            ->through(function (Sale $sale) {
                return [
                    'title' => $sale->client_name ?: 'Venta #'.$sale->id,
                    'subtitle' => $sale->client_address ?: 'Direccion sin registrar',
                    'meta' => collect([
                        'Venta #'.$sale->id,
                        $sale->client_phone,
                        optional($sale->created_at)->locale('es')->isoFormat('D MMM YYYY, HH:mm'),
                    ])->filter()->implode(' · '),
                    'url' => route('sales.show', $sale->id),
                ];
            });
    }
}
