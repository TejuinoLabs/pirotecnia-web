<?php

namespace Tests\Feature\Performance;

use App\Core\UseCases\Events\GetAllEvents;
use App\Core\UseCases\Events\GetEventsByEmployee;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Package;
use App\Models\Product;
use App\Services\Inventory\EventCoverageService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryDemandService;
use App\Services\Performance\EventPdfCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_page_query_count_does_not_grow_with_the_catalog(): void
    {
        $type = InventoryData::insert('event_types', ['name' => 'Especial', 'description' => 'Show']);
        $employee = new Employee;
        $employee->forceFill(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '3121234567', 'salary' => '500'])->save();
        $package = Package::create(['name' => 'Show', 'description' => 'Show', 'price' => 1000]);
        foreach (range(1, 60) as $number) {
            $event = $this->event($type, $number);
            $event->packages()->attach($package->id, ['price' => 1000, 'quantity' => 1]);
            $event->employees()->attach($employee->id);
        }
        $this->measure(function () {
            $page = app(GetAllEvents::class)->execute(2);
            $this->assertSame(60, $page['total']);
            $this->assertCount(10, $page['events']);
            $this->assertSame(2, $page['current_page']);
            $this->assertSame(50, $page['events']->first()->id);
        }, 12);
        $this->measure(function () use ($employee) {
            $page = app(GetEventsByEmployee::class)->execute($employee->id, 6);
            $this->assertSame(60, $page['total']);
            $this->assertCount(10, $page['events']);
        }, 12);
    }

    public function test_demand_and_coverage_do_not_query_inside_product_requirement_loops(): void
    {
        $type = InventoryData::insert('event_types', ['name' => 'Especial', 'description' => 'Show']);
        $role = InventoryData::insert('product_roles', ['name' => 'Producto']);
        $location = InventoryData::insert('inventory_locations', ['name' => 'Almacén', 'code' => 'WH', 'type' => 'warehouse', 'active' => true]);
        $products = collect(range(1, 8))->map(function ($number) use ($role, $location) {
            $product = Product::create(['name' => 'Producto '.$number, 'description' => 'Show', 'unit' => 'pz', 'product_role_id' => $role]);
            InventoryData::insert('inventory_balances', ['product_id' => $product->id, 'location_id' => $location, 'quantity' => 100]);

            return $product;
        });
        $event = $this->event($type, 1);
        foreach (range(1, 10) as $number) {
            InventoryData::insert('event_requirements', ['event_id' => $event->id, 'requirement_type' => 'specific_product', 'product_id' => $products->first()->id, 'quantity_required' => 2, 'snapshot' => []]);
        }
        $this->measure(function () use ($products) {
            $demand = app(InventoryDemandService::class)->project();
            $this->assertSame(20.0, $demand[$products->first()->id]);
        }, 12);
        $this->measure(function () use ($event) {
            $coverage = app(EventCoverageService::class)->coverage($event->id, null, false);
            $this->assertSame('covered', $coverage['status']);
            $this->assertCount(10, $coverage['requirements']);
        }, 35);
    }

    public function test_dashboard_coverage_shares_stock_across_events_in_a_single_read_pass(): void
    {
        $type = InventoryData::insert('event_types', ['name' => 'Especial', 'description' => 'Show']);
        $role = InventoryData::insert('product_roles', ['name' => 'Producto']);
        $location = InventoryData::insert('inventory_locations', ['name' => 'Almacén', 'code' => 'WH', 'type' => 'warehouse', 'active' => true]);
        $product = Product::create(['name' => 'Volcán', 'description' => 'Show', 'unit' => 'pz', 'product_role_id' => $role]);
        InventoryData::insert('inventory_balances', ['product_id' => $product->id, 'location_id' => $location, 'quantity' => 3]);
        $first = $this->event($type, 1);
        $second = $this->event($type, 2);
        foreach ([$first, $second] as $event) {
            InventoryData::insert('event_requirements', ['event_id' => $event->id, 'requirement_type' => 'specific_product',
                'product_id' => $product->id, 'quantity_required' => 2, 'snapshot' => []]);
        }
        $single = app(EventCoverageService::class)->coverage($second->id, null, false);
        $this->measure(function () use ($first, $second, $single) {
            $coverage = app(EventCoverageService::class)->coverageMany([$first->id, $second->id]);
            $this->assertSame('warning', $coverage[$first->id]['status']);
            $this->assertSame('critical', $coverage[$second->id]['status']);
            $this->assertSame($single, $coverage[$second->id]);
            $this->assertSame(1.0, $coverage[$second->id]['requirements'][0]['deficit']);
        }, 35);
    }

    public function test_event_details_reuse_pdf_and_regenerate_it_when_visible_data_changes(): void
    {
        Storage::fake('public');
        $role = InventoryData::insert('roles', ['name' => 'admin', 'description' => 'Admin']);
        $admin = \App\Models\User::factory()->create(['role_id' => $role, 'phone' => '3121234567']);
        $this->actingAs($admin, 'api');
        $type = InventoryData::insert('event_types', ['name' => 'Especial', 'description' => 'Show']);
        $event = $this->event($type, 1);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('output')->twice()->andReturn('%PDF-test');
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadHTML')->twice()->andReturn($pdf);

        $this->getJson('/api/event/'.$event->id)->assertOk()->assertJsonPath('data.pdf_url', asset('storage/pdf/event_'.$event->id.'.pdf'));
        $this->getJson('/api/event/'.$event->id)->assertOk();
        $event->update(['notes' => 'Nuevo acuerdo']);
        $this->getJson('/api/event/'.$event->id)->assertOk()->assertJsonPath('data.notes', 'Nuevo acuerdo');
    }

    public function test_unchanged_pdf_is_rendered_once_and_invalidates_on_data_change_or_file_loss(): void
    {
        Storage::fake('public');
        $renders = 0;
        $render = function () use (&$renders) {
            $renders++;

            return '%PDF-test-'.$renders;
        };
        $cache = app(EventPdfCache::class);
        $first = $cache->store(1, 'version-1', $render);
        $this->assertSame($first, $cache->store(1, 'version-1', $render));
        $this->assertSame(1, $renders);
        $cache->store(1, 'version-2', $render);
        $this->assertSame(2, $renders);
        Storage::disk('public')->delete('pdf/event_1.pdf');
        $cache->store(1, 'version-2', $render);
        $this->assertSame(3, $renders);
    }

    private function event(int $type, int $number): Event
    {
        return Event::create(['event_type_id' => $type, 'date' => now()->format('Y-m-d'), 'client_name' => 'Cliente '.$number,
            'phone' => '3121234567', 'event_address' => 'Salón', 'event_date' => now()->addMonth()->format('Y-m-d H:i:s'),
            'discount' => 0, 'advance' => 0, 'travel_expenses' => 0]);
    }

    private function measure(callable $callback, int $maximumQueries): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $callback();
            $queries = DB::getQueryLog();
            $this->assertLessThanOrEqual($maximumQueries, count($queries), 'Unexpected read amplification: '.count($queries).' queries');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
