<?php

namespace App\Http\Middleware;

use App\Services\Performance\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MeasurePerformance
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('performance.enabled')) {
            return $next($request);
        }
        $metrics = new RequestMetrics;
        $request->attributes->set(RequestMetrics::class, $metrics);
        $start = hrtime(true);
        $cpu = $this->cpuMs();
        try {
            return $next($request);
        } finally {
            $elapsed = (hrtime(true) - $start) / 1e6;
            if ($elapsed >= config('performance.request_ms') || $metrics->queries >= config('performance.query_count') || $metrics->slowestMs >= config('performance.query_ms')) {
                Log::channel('performance')->warning('slow_request', [
                    'method' => $request->method(),
                    'route' => $request->route()?->uri(),
                    'duration_ms' => round($elapsed, 2),
                    'php_cpu_ms' => round(max(0, $this->cpuMs() - $cpu), 2),
                    'database_ms' => round($metrics->databaseMs, 2),
                    'query_count' => $metrics->queries,
                    'slowest_query_ms' => round($metrics->slowestMs, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                    'slow_queries' => $metrics->slowQueries,
                ]);
            }
            $request->attributes->remove(RequestMetrics::class);
        }
    }

    private function cpuMs(): float
    {
        if (! function_exists('getrusage')) {
            return 0;
        }
        $usage = getrusage();

        return ($usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec']) * 1000
            + ($usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec']) / 1000;
    }
}
