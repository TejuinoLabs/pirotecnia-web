<?php

namespace App\Services\Panel;

use Illuminate\Support\Facades\DB;

class InventorySettingsPresentation
{
    public function forSection(string $section, ?string $catalog): array
    {
        $definitions = [
            'products' => ['Productos', 'Administra artículos, sus características y el comportamiento de su inventario.', 'products', 'inventory_2', 'Nuevo producto'],
            'categories' => ['Categorías', 'Organiza los productos para definir compatibilidad y requerimientos de los paquetes.', 'categories', 'category', 'Nueva categoría'],
            'attributes' => ['Atributos', 'Define las características técnicas que comparten los productos.', 'attribute_definitions', 'tune', 'Nuevo atributo'],
            'effects' => ['Efectos', 'Administra los efectos artísticos disponibles para tus espectáculos.', 'effects', 'auto_awesome', 'Nuevo efecto'],
            'colors' => ['Colores', 'Configura los colores disponibles para productos, perfiles y requerimientos.', 'colors', 'palette', 'Nuevo color'],
            'locations' => ['Ubicaciones', 'Administra almacenes, destinos y ubicaciones del inventario.', 'inventory_locations', 'location_on', 'Nueva ubicación'],
            'profiles' => ['Perfiles de producto', 'Agrupa especificaciones y restricciones reutilizables para preparar los eventos.', 'product_profiles', 'checklist', 'Nuevo perfil'],
            'rules' => ['Reglas de inventario', 'Configura la protección de existencias y las preferencias para asignar productos.', 'inventory_protection_rules', 'shield', 'Nueva regla'],
            'requirements' => ['Requerimientos de paquetes', 'Define los productos, materiales y equipos necesarios para cada espectáculo.', 'package_requirements', 'list_alt', 'Nuevo requerimiento'],
        ];
        [$title, $description, $table, $icon, $createLabel] = $definitions[$section === 'catalogs' ? $catalog : $section];
        $total = DB::table($table)->count();
        if ($section === 'requirements') {
            $stats = [
                ['label' => 'Requerimientos', 'value' => $total, 'icon' => $icon, 'hint' => 'Composición operativa de los paquetes'],
                ['label' => 'Paquetes configurados', 'value' => DB::table($table)->distinct()->count('package_id'), 'icon' => 'inventory_2', 'hint' => 'Con requerimientos registrados', 'tone' => 'text-secondary'],
            ];
        } else {
            $active = DB::table($table)->where('active', true)->count();
            if ($section === 'rules') {
                $total += DB::table('allocation_rules')->count();
                $active += DB::table('allocation_rules')->where('active', true)->count();
            }
            $stats = [
                ['label' => 'Total de registros', 'value' => $total, 'icon' => $icon, 'hint' => 'Registros del catálogo'],
                ['label' => 'Activos', 'value' => $active, 'icon' => 'check_circle', 'hint' => 'Disponibles para configurar', 'tone' => 'text-secondary'],
                ['label' => 'Inactivos', 'value' => $total - $active, 'icon' => 'pause_circle', 'hint' => 'Desactivados en el catálogo', 'tone' => 'text-warning'],
            ];
        }

        return compact('title', 'description', 'icon', 'createLabel', 'stats');
    }
}
