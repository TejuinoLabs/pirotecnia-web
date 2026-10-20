<?php

namespace App\Console\Commands;

use App\Core\UseCases\Events\GetAllEvents;
use App\Services\Inventory\InventoryDemandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditPerformance extends Command
{
    protected $signature = 'performance:audit {--measure : Execute read-only event-page and demand measurements}';

    protected $description = 'Report database indexes and EXPLAIN plans without exposing customer data';

    public function handle(): int
    {
        $tables = ['events', 'employees', 'employee_event', 'event_package', 'equipment_event', 'productables', 'products',
            'event_requirements', 'requirement_allocations', 'event_dispatches', 'inventory_audits', 'inventory_movements'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->line(json_encode(['table' => $table, 'indexes' => array_map(fn ($index) => [
                    'name' => $index['name'], 'columns' => $index['columns'],
                ], Schema::getIndexes($table))], JSON_THROW_ON_ERROR));
            }
        }
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach ([
                'event_page' => ['select id from events order by date desc, id desc limit 10', []],
                'upcoming_events' => ['select id from events where event_date >= ? order by event_date, id limit 20', [now()->toDateTimeString()]],
                'employee_assignments' => ['select event_id from employee_event where employee_id = ?', [1]],
            ] as $name => [$sql, $bindings]) {
                $this->line(json_encode(['explain' => $name, 'plan' => DB::select('EXPLAIN '.$sql, $bindings)], JSON_THROW_ON_ERROR));
            }
        }
        if ($this->option('measure')) {
            foreach ([
                'event_page' => fn () => app(GetAllEvents::class)->execute(1),
                'inventory_demand' => fn () => app(InventoryDemandService::class)->project(),
            ] as $name => $callback) {
                // Count without retaining SQL/bindings or enabling the unbounded query log.
                $measurement = (object) ['queries' => 0, 'database_ms' => 0, 'active' => true];
                DB::listen(function ($query) use ($measurement) {
                    if ($measurement->active) {
                        $measurement->queries++;
                        $measurement->database_ms += $query->time;
                    }
                });
                $start = hrtime(true);
                try {
                    $callback();
                } finally {
                    $measurement->active = false;
                }
                $this->line(json_encode(['measurement' => $name, 'ms' => round((hrtime(true) - $start) / 1e6, 2),
                    'queries' => $measurement->queries, 'database_ms' => round($measurement->database_ms, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)], JSON_THROW_ON_ERROR));
            }
        }

        return self::SUCCESS;
    }
}
