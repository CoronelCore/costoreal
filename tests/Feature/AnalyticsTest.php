<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Reporting\FinancialSummaryData;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $user, array $extra = []): Trip
    {
        return Trip::create(array_merge(['user_id' => $user->id, 'nombre' => 'Importación de prueba', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo'], $extra));
    }

    private function product(Trip $trip, string $purchase, string $assigned = '0', ?string $price = null, int $quantity = 1): void
    {
        $final = (string) BigDecimal::of($purchase)->plus($assigned);
        $trip->products()->create(['nombre' => 'Línea de prueba', 'moneda' => 'CLP', 'cantidad' => $quantity, 'costo_unitario' => '1', 'subtotal_clp' => $purchase, 'gastos_prorrateados_clp' => $assigned, 'costo_final_total_clp' => $final, 'costo_final_unitario_clp' => '1', 'precio_venta' => $price]);
    }

    private function expense(Trip $trip, string $amount, ?ExpenseCategory $category = null): void
    {
        $trip->expenses()->create(['concepto' => 'Flete de prueba', 'moneda' => 'CLP', 'monto' => $amount, 'monto_clp' => $amount, 'expense_category_id' => $category?->id]);
    }

    private function data(string $query = ''): array
    {
        return $this->get('/analytics'.$query)->assertOk()->viewData('page')['props'];
    }

    public function test_totals_and_projection_reconcile_with_report_source_without_writes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $a = $this->trip($user, ['fecha_inicio' => '2026-01-15']);
        $b = $this->trip($user, ['fecha_inicio' => '2026-02-15', 'estado' => 'finalizado']);
        $category = ExpenseCategory::create(['nombre' => 'Logística']);
        $this->product($a, '100.25', '10.10', '80.50', 2);
        $this->product($a, '200.25');
        $this->expense($a, '20.20', $category);
        $this->product($b, '400.00', '30.00', '450.00');
        $this->expense($b, '30.00', $category);
        $before = DB::table('products')->get()->toJson();
        $data = $this->data();
        $this->assertSame('700.50', $data['metrics']['purchase']);
        $this->assertSame('50.20', $data['metrics']['expenses']);
        $this->assertSame('750.70', $data['metrics']['total']);
        $this->assertSame('375.35', $data['metrics']['average']);
        $this->assertSame('10.10', $data['metrics']['unassigned']);
        $this->assertSame(3, $data['projection']['lines']);
        $this->assertSame(2, $data['projection']['priced_lines']);
        $this->assertSame(3, $data['projection']['priced_units']);
        $this->assertFalse($data['projection']['complete']);
        $this->assertNull($data['projection']['profit']);
        $this->assertSame('611', $data['projection']['covered_revenue']);
        $this->assertSame('71', $data['projection']['covered_profit']);
        $this->assertSame('50.20', $data['categories']['data'][0]['total']);
        foreach ($data['imports']['data'] as $row) {
            $summary = FinancialSummaryData::fromTrip(Trip::findOrFail($row['id']));
            $this->assertSame($summary->projection, $row['projection']);
            $this->assertSame($summary->statistics['costo_total'], $row['total']);
        }
        foreach (['imports', 'months'] as $table) {
            $sum = array_reduce($data[$table]['data'], fn ($sum, $row) => $sum->plus($row['total']), BigDecimal::zero());
            $this->assertSame('750.70', (string) $sum);
        }
        $this->assertSame($before, DB::table('products')->get()->toJson());
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('cost_runs', 0);
    }

    public function test_complete_negative_profit_deducts_unassigned_expenses_and_zero_prices_stay_pending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = $this->trip($user);
        $this->product($trip, '100', '0', '90');
        $this->expense($trip, '20');
        $data = $this->data();
        $this->assertSame('-30', $data['projection']['profit']);
        $this->assertSame('-33.33', $data['projection']['margin']);
        $this->assertTrue($data['projection']['complete']);
        $trip->products()->update(['precio_venta' => '0']);
        $data = $this->data();
        $this->assertSame(0, $data['projection']['priced_lines']);
        $this->assertNull($data['projection']['profit']);
        $this->assertNull($data['projection']['margin']);
    }

    public function test_date_boundaries_state_trash_and_undated_imports_use_one_cohort(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['2026-01-01', 'activo'], ['2026-01-31', 'activo'], ['2026-01-15', 'finalizado'], ['2025-12-31', 'activo'], [null, 'activo']] as [$date,$state]) {
            $trip = $this->trip($user, ['fecha_inicio' => $date, 'estado' => $state]);
            $this->product($trip, '100');
            $this->expense($trip, '10');
        }
        $trash = $this->trip($user, ['fecha_inicio' => '2026-01-15']);
        $this->product($trash, '9999');
        $trash->delete();
        $data = $this->data('?from=2026-01-01&to=2026-01-31&state=activo');
        $this->assertSame(2, $data['metrics']['count']);
        $this->assertSame('220.00', $data['metrics']['total']);
        $this->assertSame(2, $data['projection']['lines']);
        $this->assertCount(2, $data['latestExpenses']);
        $this->assertSame('20.00', $data['categories']['data'][0]['total']);
        $this->assertSame([['month' => '2026-01', 'count' => 2, 'purchase' => '200.00', 'expenses' => '20.00', 'total' => '220.00']], $data['months']['data']);
        $all = $this->data();
        $this->assertSame(5, $all['metrics']['count']);
        $this->assertSame('undated', end($all['months']['data'])['month']);
    }

    public function test_owner_and_active_company_scope_every_panel_and_foreign_category_metadata_is_hidden(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($user);
        $company = Company::create(['user_id' => $user->id, 'nombre' => 'Activa']);
        $other = Company::create(['user_id' => $user->id, 'nombre' => 'Otra']);
        $private = ExpenseCategory::create(['user_id' => $foreign->id, 'nombre' => 'SECRETO AJENO']);
        foreach ([[$user, $company->id, '10'], [$user, $other->id, '100'], [$foreign, null, '1000']] as [$owner,$companyId,$amount]) {
            $trip = $this->trip($owner, ['company_id' => $companyId]);
            $this->product($trip, $amount);
            $this->expense($trip, $amount, $private);
        }
        $this->withSession(['active_company_id' => $company->id]);
        $data = $this->data();
        $this->assertSame('20.00', $data['metrics']['total']);
        $this->assertSame(1, $data['imports']['total']);
        $this->assertCount(1, $data['latestExpenses']);
        $this->assertSame('Sin categoría', $data['categories']['data'][0]['name']);
        $serialized = json_encode(array_intersect_key($data, array_flip(['metrics', 'imports', 'categories', 'months', 'latestExpenses', 'projection'])));
        $this->assertStringNotContainsString('SECRETO AJENO', $serialized);
        foreach (['user_id', 'document_id', 'storage_path', 'imagen', 'comprobante', 'password', 'rate_source'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $serialized);
        }
    }

    public function test_concurrent_revision_change_rejects_a_mixed_financial_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = $this->trip($user);
        $this->product($trip, '100');
        $armed = true;
        DB::listen(function ($query) use (&$armed, $trip) {
            if ($armed && str_contains($query->sql, 'from "products"')) {
                $armed = false;
                DB::table('trips')->where('id', $trip->id)->increment('revision');
            }
        });
        $response = $this->getJson('/analytics')->assertUnprocessable()->assertJsonValidationErrors('analytics');
        $this->assertNull($response->json('metrics'));
        $this->assertFalse($armed);
    }

    public function test_a_new_import_during_the_read_is_rejected_without_serializing_an_unknown_parent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->trip($user);
        $armed = true;
        DB::listen(function ($query) use (&$armed, $user) {
            if ($armed && str_contains($query->sql, 'from "trips"')) {
                $armed = false;
                $newTrip = $this->trip($user);
                $this->product($newTrip, '999');
            }
        });
        $response = $this->getJson('/analytics')->assertUnprocessable()->assertJsonValidationErrors('analytics');
        $this->assertNull($response->json('metrics'));
        $this->assertFalse($armed);
    }

    public function test_empty_cohort_has_no_fictitious_profit_or_months(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->data();
        $this->assertSame(0, $data['metrics']['count']);
        $this->assertSame('0', $data['metrics']['total']);
        $this->assertFalse($data['projection']['complete']);
        $this->assertNull($data['projection']['profit']);
        foreach (['imports', 'categories', 'months'] as $table) {
            $this->assertSame([], $data[$table]['data']);
        }
        $this->assertSame([], $data['latestExpenses']);
    }

    public function test_invalid_filters_reject_before_reading_financial_data(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['?state=desconocido', 'state'], ['?from=2026-02-30', 'from'], ['?from=2026-02-01&to=2026-01-01', 'to'], ['?imports_page=0', 'imports_page'], ['?months_page=100001', 'months_page']] as [$query,$field]) {
            $this->getJson('/analytics'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_pagination_is_bounded_keeps_whole_cohort_totals_and_filter_urls(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($i = 0; $i < 25; $i++) {
            $trip = $this->trip($user, ['fecha_inicio' => now()->startOfMonth()->subMonths($i)->toDateString()]);
            $category = ExpenseCategory::create(['user_id' => $user->id, 'nombre' => 'Categoría '.$i]);
            $this->product($trip, '100');
            $this->expense($trip, '10', $category);
        }
        $data = $this->data('?state=activo&imports_page=2&categories_page=2&months_page=2');
        $this->assertSame('2750.00', $data['metrics']['total']);
        $this->assertCount(10, $data['imports']['data']);
        $this->assertCount(10, $data['categories']['data']);
        $this->assertCount(12, $data['months']['data']);
        $this->assertCount(10, $data['latestExpenses']);
        foreach (['imports', 'categories', 'months'] as $table) {
            $this->assertSame(25, $data[$table]['total']);
            $this->assertSame(2, $data[$table]['current_page']);
            $this->assertStringContainsString('state=activo', $data[$table]['next_page_url']);
        }
        $last = $this->data('?imports_page=99999');
        $this->assertSame(3, $last['imports']['current_page']);
        $this->assertCount(5, $last['imports']['data']);
        $this->assertSame('2750.00', $last['metrics']['total']);
    }

    public function test_streamed_read_has_fixed_query_count_at_200_imports_and_preserves_large_decimal_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = $this->trip($user);
        $this->product($trip, '9000000000000000');
        DB::enableQueryLog();
        $this->data();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 1; $i < 200; $i++) {
            $trip = $this->trip($user);
            $this->product($trip, '9000000000000000');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = $this->data();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame('1800000000000000000.00', $data['metrics']['total']);
        $this->assertSame(200, $data['projection']['lines']);
        $this->assertCount(10, $data['imports']['data']);
        $this->assertSame($one, $many);
        $this->assertLessThanOrEqual(7, $many);
        fwrite(STDOUT, "\nAnalítica exacta: $one consultas con 1 importación; $many con 200.\n");
    }
}
