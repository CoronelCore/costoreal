<?php

namespace App\Providers;

use App\Modules\Costing\CalculationMemo;
use App\Modules\Observability\RequestMetrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestMetrics::class);
        $this->app->scoped(CalculationMemo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Resolve current request scope inside the listener; do not retain a previous worker request.
        DB::listen(fn (QueryExecuted $query) => app(RequestMetrics::class)->query($query->time));
    }
}
