<?php

namespace App\Http\Middleware;

use App\Modules\Observability\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ObservePerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()?->getName();
        $budget = config('performance.routes', [])[$route] ?? null;
        if (! config('performance.enabled') || ! is_array($budget)) {
            return $next($request);
        }
        $metrics = app(RequestMetrics::class);
        $metrics->begin();
        $response = null;
        try {
            return $response = $next($request);
        } finally {
            $values = $metrics->finish();
            $bytes = $response instanceof BinaryFileResponse ? $response->getFile()->getSize()
                : ($response && is_string($response->getContent()) ? strlen($response->getContent()) : null);
            if ($bytes === null && $response && ctype_digit((string) $response->headers->get('Content-Length'))) {
                $bytes = (int) $response->headers->get('Content-Length');
            }
            $exceeded = [];
            foreach (['elapsed_ms', 'query_count'] as $key) {
                if ($values[$key] > $budget[$key]) {
                    $exceeded[] = $key;
                }
            }
            if ($bytes !== null && $bytes > $budget['response_bytes']) {
                $exceeded[] = 'response_bytes';
            }
            $record = ['route' => $route, 'method' => $request->method(), 'status' => $response?->getStatusCode() ?? 500,
                ...$values, 'response_bytes' => $bytes, 'budget' => $budget, 'exceeded' => $exceeded];
            // Available to the local kernel profiler, never attached to headers or Inertia props.
            $request->attributes->set('costoreal.performance', $record);
            if ($exceeded || config('performance.log_all')) {
                try {
                    Log::channel('performance')->log($exceeded ? 'warning' : 'info', $exceeded ? 'performance.budget_exceeded' : 'performance.sample', $record);
                } catch (Throwable) {
                    // A failed diagnostic write must not turn a committed operation into an error.
                }
            }
        }
    }
}
