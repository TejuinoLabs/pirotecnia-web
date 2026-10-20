<?php

return [
    'enabled' => (bool) env('PERFORMANCE_MONITOR_ENABLED', false),
    'request_ms' => (int) env('PERFORMANCE_REQUEST_MS', 1000),
    'query_ms' => (int) env('PERFORMANCE_QUERY_MS', 200),
    'query_count' => (int) env('PERFORMANCE_QUERY_COUNT', 100),
    'pdf_cache_seconds' => (int) env('EVENT_PDF_CACHE_SECONDS', 300),
];
