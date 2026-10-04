<?php

namespace App\Modules\Observability;

/** Request-scoped counters only: never retains SQL, bindings or user data. */
final class RequestMetrics
{
    private bool $active = false;

    private int $started = 0;

    private int $queries = 0;

    private float $databaseMs = 0;

    public function begin(): void
    {
        $this->queries = 0;
        $this->databaseMs = 0;
        $this->started = hrtime(true);
        $this->active = true;
    }

    public function query(float $milliseconds): void
    {
        if ($this->active) {
            $this->queries++;
            $this->databaseMs += $milliseconds;
        }
    }

    public function finish(): array
    {
        $elapsed = $this->active ? (hrtime(true) - $this->started) / 1e6 : 0;
        $this->active = false;

        return ['elapsed_ms' => round($elapsed, 2), 'query_count' => $this->queries, 'database_ms' => round($this->databaseMs, 2),
            'process_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2)];
    }
}
