<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Costing\CalculationMemo;
use App\Modules\Observability\RequestMetrics;
use App\Support\Installation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // People, invitations and comments belong to whoever installed this copy, not to every account.
        Gate::define('manage-installation', fn (User $user) => Installation::isOwner($user));

        // One rule for every password set on the web: installer, invitation, registration, reset and profile.
        Password::defaults(fn () => Password::min(10)->letters()->numbers());
    }
}
