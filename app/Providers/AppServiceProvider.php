<?php

namespace App\Providers;

use App\View\Composers\CompanyComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Compartir datos de empresas en todas las vistas
        View::composer('*', CompanyComposer::class);
    }
}
