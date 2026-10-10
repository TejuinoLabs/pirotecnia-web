<?php

namespace App\Presentation\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    public function indexSettings()
    {
        $definitions = [
            ['Paquetes', 'Promesas comerciales, material, equipo y requerimientos del espectáculo.', 'settings.packages.index', 'packages', 'inventory_2', null],
            ['Productos', 'Artículos, SKU, atributos, efectos, colores y comportamiento de inventario.', 'settings.products.index', 'products', 'explosion', 'inventory.view'],
            ['Equipamiento', 'Catálogo del equipo técnico utilizado en los eventos.', 'settings.equipments.index', 'equipments', 'construction', null],
            ['Tipos de cliente', 'Clasificación de clientes y condiciones comerciales.', 'settings.client-types.index', 'client_types', 'groups', null],
            ['Tipos de evento', 'Clasificación de espectáculos y eventos.', 'settings.event_types.index', 'event_types', 'festival', null],
            ['Niveles de experiencia', 'Niveles requeridos para el personal técnico.', 'settings.experience-levels.index', 'experience_levels', 'badge', null],
            ['Categorías', 'Agrupación de artículos para compatibilidad y requerimientos.', 'settings.inventory.categories', 'categories', 'category', 'inventory.view'],
            ['Atributos', 'Definiciones de tiros, calibre y otras características.', 'settings.inventory.attributes', 'attribute_definitions', 'tune', 'inventory.view'],
            ['Efectos', 'Efectos artísticos disponibles para los productos.', 'settings.inventory.effects', 'effects', 'auto_awesome', 'inventory.view'],
            ['Colores', 'Colores disponibles para productos y reglas de protección.', 'settings.inventory.colors', 'colors', 'palette', 'inventory.view'],
            ['Ubicaciones', 'Almacenes, destinos y adopción de inventarios anteriores.', 'settings.inventory.locations', 'inventory_locations', 'location_on', 'inventory.view'],
            ['Perfiles de producto', 'Especificaciones reutilizables y restricciones de compatibilidad.', 'settings.inventory.profiles', 'product_profiles', 'checklist', 'inventory.view'],
            ['Reglas de inventario', 'Protección de existencias y prioridades de asignación.', 'inventory.rules', 'inventory_protection_rules', 'shield', 'inventory.view'],
        ];
        $modules = collect($definitions)->filter(fn ($definition) => $definition[5] === null || app(InventoryAccess::class)->allows(auth()->user(), $definition[5]))
            ->map(fn ($definition) => ['title' => $definition[0], 'description' => $definition[1], 'route' => $definition[2], 'icon' => $definition[4], 'count' => Schema::hasTable($definition[3]) ? DB::table($definition[3])->count() : 0]);

        return view('panel.settings.settings', compact('modules'));
    }
}
