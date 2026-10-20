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
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('expenses', 6);
        $this->assertNull(Company::sole()->rut);
        $trip = Trip::sole();
        $this->assertEquals('1485000', $trip->total_productos);
        $this->assertEquals('385000', $trip->total_gastos);
        $this->assertEquals('1870000', $trip->costo_total);
        $this->assertDatabaseCount('cost_runs', 1);
        $this->assertTrue(app(ReviewImportation::class)->execute($trip)['can_close']);
        $this->actingAs($user)->get('/trips')->assertOk();
        $this->get('/trips/'.$trip->id)->assertOk();
        $this->get('/analytics')->assertOk();
        $this->get('/trips/'.$trip->id.'/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/trips/'.$trip->id.'/export/excel')->assertOk();
        app(CloseImportation::class)->execute($trip, $trip->revision, AccessContext::forActor($user->id));
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertDatabaseCount('cost_runs', 2);
        $this->artisan('costoreal:demo', ['email' => $user->email])->assertFailed();
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('trips', 1);
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('expenses', 6);
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
