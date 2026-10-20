<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexes(): array
    {
        return [
            'events' => [
                'perf_events_date_id' => ['date', 'id'],
                'perf_events_event_date_id' => ['event_date', 'id'],
            ],
            'employees' => ['perf_employees_user_status' => ['user_id', 'status']],
            'employee_event' => [
                'perf_employee_event_employee_event' => ['employee_id', 'event_id'],
                'perf_employee_event_event_employee' => ['event_id', 'employee_id'],
            ],
            'event_package' => ['perf_event_package_event_package' => ['event_id', 'package_id']],
            'equipment_event' => ['perf_equipment_event_event_equipment' => ['event_id', 'equipment_id']],
            'productables' => ['perf_productables_product_owner' => ['product_id', 'productable_type', 'productable_id']],
            'event_requirements' => ['perf_event_requirements_event_status' => ['event_id', 'status']],
            'requirement_allocations' => [
                'perf_allocations_product_status_location' => ['product_id', 'status', 'warehouse_location_id'],
                'perf_allocations_requirement_status' => ['event_requirement_id', 'status'],
            ],
            'event_dispatches' => ['perf_dispatches_event_technician_status' => ['event_id', 'technician_id', 'status']],
            'inventory_audits' => ['perf_audits_entity_action' => ['entity_type', 'entity_id', 'action']],
            'inventory_movements' => ['perf_movements_product_occurred' => ['product_id', 'occurred_at']],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = Schema::getIndexes($table);
            foreach ($definitions as $name => $columns) {
                // A longer existing index already covers this leftmost prefix.
                $covered = collect($existing)->contains(fn ($index) => array_slice($index['columns'], 0, count($columns)) === $columns);
                if (! $covered) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($definitions as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
