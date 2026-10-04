<?php

// Local operational budgets, not production SLAs. Profiling evidence and actions: docs/PERFORMANCE.md.
$read = ['elapsed_ms' => 750, 'query_count' => 20, 'response_bytes' => 1500000,
    'action' => 'Repetir el perfil aislado; revisar consultas y tamaño de props antes de modificar límites.'];
$write = ['elapsed_ms' => 2500, 'query_count' => 150, 'response_bytes' => 100000,
    'action' => 'Revisar lotes, reparto y transacciones con el fixture del mismo volumen; conservar atomicidad.'];

return [
    'enabled' => env('COSTOREAL_PERFORMANCE_ENABLED', true),
    'log_all' => env('COSTOREAL_PERFORMANCE_LOG_ALL', false),
    'routes' => [
        'trips.index' => $read,
        'trips.show' => $read,
        'analytics.index' => [...$read, 'elapsed_ms' => 2000],
        'companies.index' => $read,
        'companies.show' => $read,
        'trips.review' => [...$read, 'elapsed_ms' => 2000],
        'trips.cost-runs.index' => $read,
        'trips.cost-breakdown.show' => $read,
        'trips.audit.index' => $read,
        'trips.scenarios.index' => $read,
        'trips.documents.show' => [...$read, 'response_bytes' => 10485760],
        'trips.export.excel' => [...$read, 'elapsed_ms' => 1500, 'query_count' => 20],
        'trips.export.pdf' => [...$read, 'elapsed_ms' => 1500, 'query_count' => 20],
        'trips.products.store' => $write,
        'trips.products.update' => $write,
        'trips.products.destroy' => $write,
        'trips.expenses.store' => $write,
        'trips.expenses.update' => $write,
        'trips.expenses.destroy' => $write,
        'trips.products.import.preview' => [...$write, 'response_bytes' => 1500000],
        'trips.products.import.apply' => $write,
        'trips.pricing.preview' => [...$read, 'response_bytes' => 1500000],
        'trips.pricing.apply' => $write,
        'trips.scenarios.preview' => [...$write, 'response_bytes' => 1500000],
        'trips.scenarios.store' => [...$write, 'response_bytes' => 1500000],
        'trips.scenarios.apply' => $write,
        'trips.close' => $write,
        'trips.reopen' => $write,
    ],
];
