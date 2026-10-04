<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripRecoveryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->scopeBindings()->group(function () {
    // Redirigir dashboard a trips
    Route::get('/dashboard', function () {
        return redirect()->route('trips.index');
    })->name('dashboard');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Trip routes
    Route::get('trips/trash', [TripRecoveryController::class, 'index'])->name('trips.trash');
    Route::post('trips/{trip}/restore', [TripRecoveryController::class, 'restore'])->withTrashed()->name('trips.restore');
    Route::resource('trips', TripController::class);
    Route::patch('trips/{trip}/close', [TripController::class, 'close'])->name('trips.close');
    Route::patch('trips/{trip}/reopen', [TripController::class, 'reopen'])->name('trips.reopen');

    // Company routes
    Route::resource('companies', CompanyController::class);
    Route::get('/companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');
    Route::get('/company/switch/{companyId?}', [CompanyController::class, 'switch'])->name('company.switch');
    Route::post('/companies/{company}/assign-trips', [CompanyController::class, 'assignTrips'])->name('companies.assign-trips');

    // Analytics route
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Category routes
    Route::resource('categories', ExpenseCategoryController::class)->except(['show']);

    // Export routes
    Route::get('trips/{trip}/export/pdf', [ExportController::class, 'exportPdf'])->name('trips.export.pdf');
    Route::get('trips/{trip}/export/excel', [ExportController::class, 'exportExcel'])->name('trips.export.excel');

    Route::post('trips/{trip}/documents', [DocumentController::class, 'store'])->name('trips.documents.store');
    Route::get('trips/{trip}/documents/{document}', [DocumentController::class, 'show'])->name('trips.documents.show');
    Route::delete('trips/{trip}/documents/{document}', [DocumentController::class, 'destroy'])->name('trips.documents.destroy');
    Route::post('trips/{trip}/documents/{document}/restore', [DocumentController::class, 'restore'])->withTrashed()->name('trips.documents.restore');

    Route::post('trips/{trip}/pricing/preview', [PricingController::class, 'preview'])->name('trips.pricing.preview');
    Route::post('trips/{trip}/pricing/apply', [PricingController::class, 'apply'])->name('trips.pricing.apply');

    Route::post('trips/{trip}/products/import/preview', [ProductImportController::class, 'preview'])->name('trips.products.import.preview');
    Route::post('trips/{trip}/products/import/apply', [ProductImportController::class, 'apply'])->name('trips.products.import.apply');

    Route::get('trips/{trip}/scenarios', [ScenarioController::class, 'index'])->name('trips.scenarios.index');
    Route::post('trips/{trip}/scenarios/preview', [ScenarioController::class, 'preview'])->name('trips.scenarios.preview');
    Route::post('trips/{trip}/scenarios', [ScenarioController::class, 'store'])->name('trips.scenarios.store');
    Route::get('trips/{trip}/scenarios/{scenario}', [ScenarioController::class, 'show'])->name('trips.scenarios.show');
    Route::post('trips/{trip}/scenarios/{scenario}/apply', [ScenarioController::class, 'apply'])->name('trips.scenarios.apply');
    Route::get('trips/{trip}/audit-events', [AuditController::class, 'index'])->name('trips.audit.index');
    Route::get('trips/{trip}/audit-events/{auditEvent}', [AuditController::class, 'show'])->name('trips.audit.show');

    // Nested routes for expenses and products
    Route::post('trips/{trip}/expenses', [ExpenseController::class, 'store'])->name('trips.expenses.store');
    Route::put('trips/{trip}/expenses/{expense}', [ExpenseController::class, 'update'])->name('trips.expenses.update');
    Route::delete('trips/{trip}/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('trips.expenses.destroy');

    Route::post('trips/{trip}/products', [ProductController::class, 'store'])->name('trips.products.store');
    Route::put('trips/{trip}/products/{product}', [ProductController::class, 'update'])->name('trips.products.update');
    Route::patch('trips/{trip}/products/{product}/precio-venta', [ProductController::class, 'updatePrecioVenta'])->name('trips.products.update-precio-venta');
    Route::delete('trips/{trip}/products/{product}', [ProductController::class, 'destroy'])->name('trips.products.destroy');
});

require __DIR__.'/auth.php';
