<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\CloseImportation;
use App\Modules\Importations\Review\ReviewImportation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_uses_the_current_costing_engine_and_can_be_browsed_exported_and_closed(): void
    {
        $this->seed();
        $user = User::factory()->create(['id' => 17, 'email' => 'demo@example.test']);
        $passwordHash = $user->password;
        $this->artisan('costoreal:demo', ['email' => $user->email])->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('expenses', 10);
        $this->assertNull(Company::sole()->rut);
        $this->assertDatabaseCount('expense_categories', 8);
        $this->assertSame(0, Expense::whereNull('expense_category_id')->count());
        $trip = Trip::where('moneda_trabajo', 'USD')->sole();
        $this->assertEquals('1485000', $trip->total_productos);
        $this->assertEquals('385000', $trip->total_gastos);
        $this->assertEquals('1870000', $trip->costo_total);
        $brazil = Trip::where('moneda_trabajo', 'BRL')->sole();
        $this->assertEquals('1386000', $brazil->total_productos);
        $this->assertEquals('677000', $brazil->total_gastos);
        $this->assertEquals('2063000', $brazil->costo_total);
        $this->assertSame(['562638.00', '535843.00', '964519.00'], $brazil->products()->orderBy('id')->pluck('costo_final_total_clp')->all());
        $this->assertDatabaseCount('cost_runs', 2);
        foreach ([$trip, $brazil] as $demo) {
            $this->assertTrue(app(ReviewImportation::class)->execute($demo)['can_close']);
        }
        $this->actingAs($user)->get('/trips')->assertOk();
        $this->get('/trips/'.$trip->id)->assertOk();
        $this->get('/trips/'.$brazil->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trip.products.0.resultado.compra_unitaria_clp', '6300.00000000')
            ->where('trip.products.0.resultado.costo_unitario_clp', '9377.30000000')
            ->where('trip.products.0.resultado.ganancia_unitaria_clp', '3612.70000000')
            ->where('trip.products.0.resultado.margen', '27.81')->etc());
        $this->get('/analytics')->assertOk();
        $this->get('/trips/'.$trip->id.'/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/trips/'.$trip->id.'/export/excel')->assertOk();
        app(CloseImportation::class)->execute($trip, $trip->revision, AccessContext::forActor($user->id));
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertDatabaseCount('cost_runs', 3);
        $this->artisan('costoreal:demo', ['email' => $user->email])->assertFailed();
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('trips', 2);
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('expenses', 10);
        $this->assertEquals('1870000', $trip->fresh()->costo_total);
    }

    public function test_demo_refuses_existing_data_without_changing_records(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id, 'nombre' => 'Empresa existente', 'activa' => true]);
        $this->artisan('costoreal:demo', ['email' => $user->email])->assertFailed();
        $this->assertSame('Empresa existente', $company->fresh()->nombre);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('trips', 0);
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Expense::count());
    }
}
