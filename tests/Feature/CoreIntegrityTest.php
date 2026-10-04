<?php

namespace Tests\Feature;

use App\Exports\TripProductsSheet;
use App\Exports\TripSummarySheet;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Regresiones de aislamiento, integridad y contrato financiero.
class CoreIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function tripFor(User $user, array $extra = []): Trip
    {
        return Trip::create(array_merge(['user_id' => $user->id, 'nombre' => 'Auditoría sintética', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => 900, 'estado' => 'activo'], $extra));
    }

    private function productFor(Trip $trip): Product
    {
        return Product::create(['trip_id' => $trip->id, 'nombre' => 'Producto sintético', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => 100, 'subtotal_clp' => 90000, 'costo_final_unitario_clp' => 90000, 'costo_final_total_clp' => 90000]);
    }

    private function expenseFor(Trip $trip, ?ExpenseCategory $category = null, float $clp = 9000): Expense
    {
        return Expense::create(['trip_id' => $trip->id, 'expense_category_id' => $category?->id, 'concepto' => 'Gasto sintético', 'moneda' => 'USD', 'monto' => $clp / 900, 'monto_clp' => $clp]);
    }

    public function test_foreign_product_cannot_be_changed_through_owned_trip(): void
    {
        $owner = User::factory()->create();
        $own = $this->tripFor($owner);
        $foreign = $this->productFor($this->tripFor(User::factory()->create()));
        $this->actingAs($owner)->patch("/trips/{$own->id}/products/{$foreign->id}/precio-venta", ['precio_venta' => 12345])->assertNotFound();
        $this->assertNull($foreign->fresh()->precio_venta);
    }

    public function test_foreign_expense_cannot_be_changed_through_owned_trip(): void
    {
        $owner = User::factory()->create();
        $own = $this->tripFor($owner);
        $foreign = $this->expenseFor($this->tripFor(User::factory()->create()));
        $this->actingAs($owner)->put("/trips/{$own->id}/expenses/{$foreign->id}", ['concepto' => 'Modificado por tercero', 'moneda' => 'USD', 'monto' => 50])->assertNotFound();
        $this->assertEquals('Gasto sintético', $foreign->fresh()->concepto);
    }

    public function test_foreign_company_cannot_be_assigned_to_new_trip(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Empresa ajena', 'color' => '#123456']);
        $this->actingAs($owner)->post('/trips', ['company_id' => $company->id, 'nombre' => 'Asignación ajena', 'moneda_trabajo' => 'USD', 'tipo_cambio' => 900])->assertSessionHasErrors('company_id');
        $this->assertDatabaseMissing('trips', ['user_id' => $owner->id, 'company_id' => $company->id]);
    }

    public function test_shared_category_edit_rejects_access(): void
    {
        $category = ExpenseCategory::create(['nombre' => 'Compartida', 'icono' => 'truck', 'color' => '#123456']);
        $foreign = $this->expenseFor($this->tripFor(User::factory()->create()), $category);
        $this->actingAs(User::factory()->create())->get("/categories/{$category->id}/edit")
            ->assertForbidden();
    }

    public function test_authenticated_user_cannot_rename_shared_category(): void
    {
        $category = ExpenseCategory::create(['nombre' => 'Compartida', 'icono' => 'truck', 'color' => '#123456']);
        $this->actingAs(User::factory()->create())->put("/categories/{$category->id}", ['nombre' => 'Renombrada', 'icono' => 'truck', 'color' => '#123456'])->assertForbidden();
        $this->assertEquals('Compartida', $category->fresh()->nombre);
    }

    public function test_rate_update_revalues_purchase_and_estimated_expenses(): void
    {
        $owner = User::factory()->create();
        $trip = $this->tripFor($owner);
        $expense = $this->expenseFor($trip);
        $product = $this->productFor($trip);
        $this->actingAs($owner)->put("/trips/{$trip->id}", ['nombre' => $trip->nombre, 'moneda_trabajo' => 'USD', 'tipo_cambio' => 1000, 'estado' => 'activo', 'expected_revision' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(10000, $expense->fresh()->monto_clp);
        $this->assertEquals(100000, $product->fresh()->subtotal_clp);
        $this->assertEquals(110000, $product->fresh()->costo_final_total_clp);
    }

    public function test_closed_trip_rejects_new_product(): void
    {
        $owner = User::factory()->create();
        $trip = $this->tripFor($owner, ['estado' => 'finalizado']);
        $this->actingAs($owner)->post("/trips/{$trip->id}/products", ['nombre' => 'Agregado tras cierre', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => 1])->assertForbidden();
        $this->assertDatabaseMissing('products', ['trip_id' => $trip->id, 'nombre' => 'Agregado tras cierre']);
    }

    public function test_analytics_runs_on_default_sqlite(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs(User::factory()->create())->get('/analytics')->assertOk();
    }

    public function test_category_analytics_exclude_foreign_user_and_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['user_id' => $owner->id, 'nombre' => 'Empresa activa', 'color' => '#123456']);
        $category = ExpenseCategory::create(['nombre' => 'Compartida', 'icono' => 'truck', 'color' => '#123456']);
        $this->expenseFor($this->tripFor($owner, ['company_id' => $company->id]), $category, 100);
        $this->expenseFor($this->tripFor($owner), $category, 200);
        $this->expenseFor($this->tripFor(User::factory()->create()), $category, 300);
        $this->actingAs($owner)->withSession(['active_company_id' => $company->id])->get('/analytics')
            ->assertInertia(fn (Assert $page) => $page->component('Analytics/Index')->where('gastosPorCategoria.0.total_monto', 100)->etc());
    }

    public function test_trip_index_supplies_purchase_plus_expenses(): void
    {
        $owner = User::factory()->create();
        $trip = $this->tripFor($owner);
        $this->productFor($trip);
        $this->actingAs($owner)->get('/trips')
            ->assertInertia(fn (Assert $page) => $page->component('Trips/Index')->where('trips.data.0.total_gastos_clp', '0')->where('trips.data.0.costo_total', '90000')->etc());
    }

    public function test_excel_products_use_numeric_canonical_fields(): void
    {
        $trip = $this->tripFor(User::factory()->create());
        $this->productFor($trip);
        $row = (new TripProductsSheet($trip))->array()[0];
        $this->assertSame('USD', $row[2]);
        $this->assertEquals(100, $row[3]);
        $this->assertEquals(90000, $row[4]);
        $this->assertEquals(90000, $row[7]);
        $this->assertNull($row[8]);
    }

    public function test_excel_summary_handles_optional_dates(): void
    {
        $trip = $this->tripFor(User::factory()->create());
        $rows = (new TripSummarySheet($trip))->array();
        $this->assertSame('Sin fecha', $rows[4][1]);
        $this->assertSame(0.0, $rows[14][1]);
    }

    public function test_pdf_handles_optional_expense_category(): void
    {
        $owner = User::factory()->create();
        $trip = $this->tripFor($owner);
        $this->expenseFor($trip);
        $this->withoutExceptionHandling();
        $response = $this->actingAs($owner)->get("/trips/{$trip->id}/export/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_company_assignment_is_atomic_when_forbidden(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['user_id' => $owner->id, 'nombre' => 'Propia', 'color' => '#123456']);
        $own = $this->tripFor($owner);
        $foreign = $this->tripFor(User::factory()->create());
        $this->actingAs($owner)->post("/companies/{$company->id}/assign-trips", ['trip_ids' => [$own->id, $foreign->id], 'expected_revisions' => [$own->id => 1, $foreign->id => 1]])->assertForbidden();
        $this->assertNull($own->fresh()->company_id);
    }

    public function test_measure_analytics_query_growth(): void
    {
        $owner = User::factory()->create();
        $this->tripFor($owner);
        DB::enableQueryLog();
        $this->actingAs($owner)->get('/analytics')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 1; $i < 20; $i++) {
            $this->tripFor($owner);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/analytics')->assertOk();
        $twenty = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 2, $twenty);
        fwrite(STDOUT, "\nConsultas analítica: 1 importación = $one; 20 importaciones = $twenty\n");
    }

    public function test_company_batch_conflict_rolls_back_every_revision_and_snapshot(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['user_id' => $owner->id, 'nombre' => 'Propia', 'color' => '#123456']);
        $first = $this->tripFor($owner);
        $second = $this->tripFor($owner);
        $this->actingAs($owner)->post("/companies/{$company->id}/assign-trips", ['trip_ids' => [$first->id, $second->id], 'expected_revisions' => [$first->id => 1, $second->id => 99]])->assertSessionHasErrors('conflict');
        $this->assertNull($first->fresh()->company_id);
        $this->assertSame(1, $first->fresh()->revision);
        $this->assertSame(0, $first->costRuns()->count());
        $this->assertSame(0, $first->auditEvents()->count());
        $this->post("/companies/{$company->id}/assign-trips", ['trip_ids' => [$first->id], 'expected_revisions' => [$first->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(2, $first->fresh()->revision);
        $this->assertSame('company_assignment', $first->costRuns()->firstOrFail()->reason);
        $this->assertSame('Propia', $first->costRuns()->first()->snapshot['company']['nombre']);
    }

    public function test_trashed_trip_is_not_silently_skipped_in_company_assignment(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['user_id' => $owner->id, 'nombre' => 'Propia', 'color' => '#123456']);
        $first = $this->tripFor($owner);
        $trashed = $this->tripFor($owner);
        $trashed->delete();
        $this->actingAs($owner)->post("/companies/{$company->id}/assign-trips", ['trip_ids' => [$first->id, $trashed->id], 'expected_revisions' => [$first->id => 1, $trashed->id => 1]])->assertSessionHasErrors('trip_ids.1');
        $this->assertNull($first->fresh()->company_id);
    }
}
