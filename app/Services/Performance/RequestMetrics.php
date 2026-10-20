<?php

namespace App\Services\Performance;

use Illuminate\Database\Events\QueryExecuted;

class RequestMetrics
{
    public int $queries = 0;

    public float $databaseMs = 0;

    public float $slowestMs = 0;

    public array $slowQueries = [];

    public function record(QueryExecuted $query): void
    {
        $this->queries++;
        $this->databaseMs += $query->time;
        $this->slowestMs = max($this->slowestMs, $query->time);
        if ($query->time >= config('performance.query_ms')) {
            $this->slowQueries[] = ['fingerprint' => hash('sha256', $query->sql), 'ms' => round($query->time, 2)];
            usort($this->slowQueries, fn ($a, $b) => $b['ms'] <=> $a['ms']);
            $this->slowQueries = array_slice($this->slowQueries, 0, 5);
        }
    }
}
