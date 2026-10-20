<?php

namespace Tests\Feature\Performance;

use App\Providers\AppServiceProvider;
use App\Services\Performance\RequestMetrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PerformanceMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_slow_requests_log_route_and_totals_without_customer_data(): void
    {
        config(['performance.enabled' => true, 'performance.request_ms' => 0, 'performance.query_ms' => 0]);
        (new AppServiceProvider($this->app))->boot();
        Route::middleware('api')->get('/api/performance-check/{customer}', function () {
            DB::select('select ? as customer', ['private-customer-data']);

            return response()->json(['ok' => true]);
        });
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('performance')->once()->andReturn($logger);
        $logger->shouldReceive('warning')->once()->with('slow_request', Mockery::on(function ($context) {
            $this->assertSame('api/performance-check/{customer}', $context['route']);
            $this->assertSame(1, $context['query_count']);
            $this->assertArrayHasKey('php_cpu_ms', $context);
            $this->assertArrayHasKey('database_ms', $context);
            $this->assertStringNotContainsString('private-customer-data', json_encode($context));
            $this->assertStringNotContainsString('private-id', json_encode($context));
            $this->assertArrayNotHasKey('sql', $context['slow_queries'][0]);

            return true;
        }));

        $this->getJson('/api/performance-check/private-id')->assertOk();
    }

    public function test_query_metrics_keep_only_five_slowest_queries(): void
    {
        config(['performance.query_ms' => 1]);
        $metrics = new RequestMetrics;
        foreach (range(1, 20) as $time) {
            $metrics->record(new QueryExecuted('select ?', ['private-value'], $time, DB::connection()));
        }
        $this->assertSame(20, $metrics->queries);
        $this->assertSame(210.0, $metrics->databaseMs);
        $this->assertSame(20.0, $metrics->slowestMs);
        $this->assertCount(5, $metrics->slowQueries);
        $this->assertSame([20.0, 19.0, 18.0, 17.0, 16.0], array_column($metrics->slowQueries, 'ms'));
    }
}
