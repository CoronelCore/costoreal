<?php

namespace Tests\Feature;

use App\Models\CostRun;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Costing\Actions\RecordCostRun;
use App\Modules\Costing\Domain\CostResult;
use App\Services\ProrrateoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CostBreakdownReadTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $expenseCount = 1, bool $legacy = false): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Lectura paginada', 'moneda_trabajo' => 'USD',
            'moneda_base' => 'CLP', 'tipo_cambio' => '900.12345678', 'estado' => 'activo']);
        $product = $trip->products()->create(['nombre' => 'Sensor original', 'moneda' => 'USD', 'cantidad' => 3, 'costo_unitario' => '100',
            'subtotal_clp' => '0', 'costo_final_total_clp' => '0', 'costo_final_unitario_clp' => '0',
            'rate_mode' => 'paid', 'applied_rate' => '950.12345678', 'rate_source' => 'Banco original', 'rate_date' => '2026-10-03']);
        $trip->products()->create(['nombre' => 'Otra línea', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100',
            'subtotal_clp' => '0', 'costo_final_total_clp' => '0', 'costo_final_unitario_clp' => '0']);
        $rows = array_map(fn ($i) => ['trip_id' => $trip->id, 'concepto' => "Gasto original {$i}", 'moneda' => 'CLP', 'monto' => '101', 'monto_clp' => '101'], range(1, $expenseCount));
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('expenses')->insert($chunk);
        }
        $trip = $trip->fresh();
        $result = app(ProrrateoService::class)->calcularProrrateo($trip);
        if ($legacy) {
            $result = new CostResult($result->lines, array_map(fn ($row) => array_diff_key($row, ['trace' => true]), $result->toArray()['allocations']),
                $result->purchase, $result->expenses, $result->assigned, $result->unassigned, $result->total);
        }
        $run = app(RecordCostRun::class)->execute($trip, $result, 'test_fixture');

        return [$trip, $product, $run];
    }

    private function url(Trip $trip, CostRun $run, int $product): string
    {
        return "/trips/{$trip->id}/cost-runs/{$run->id}/products/{$product}";
    }

    public function test_initial_detail_never_reads_full_cost_run_payloads(): void
    {
        [$trip, $product, $run] = $this->fixture(40);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get("/trips/{$trip->id}")->assertInertia(fn (Assert $page) => $page
            ->where('latestCostRun.id', $run->id)->missing('latestCostRun.result')->missing('latestCostRun.snapshot')->missing('costBreakdown')->etc());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $reads = $queries->filter(fn ($sql) => str_contains($sql, 'from "cost_runs"'));
        $this->assertCount(1, $reads);
        $this->assertStringNotContainsString('"result"', $reads->first());
        $this->assertStringNotContainsString('"snapshot"', $reads->first());
        $this->assertStringNotContainsString('"input"', $reads->first());
        $this->assertEmpty($queries->filter(fn ($sql) => str_contains($sql, 'cost_allocations')));
    }

    public function test_twenty_and_five_hundred_expenses_have_the_same_read_query_budget_and_bounded_payload(): void
    {
        $counts = [];
        $bytes = [];
        foreach ([20, 500] as $size) {
            [$trip, $product, $run] = $this->fixture($size);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->getJson($this->url($trip, $run, $product->id))->assertOk()
                ->assertJsonCount(20, 'allocations.data')->assertJsonPath('allocations.total', $size)
                ->assertJsonPath('line.rate', '950.12345678')->assertJsonPath('line.rate_source', 'Banco original');
            $queries = DB::getQueryLog();
            $counts[] = count($queries);
            DB::disableQueryLog();
            $this->assertLessThan(16000, strlen($response->getContent()));
            $bytes[] = strlen($response->getContent());
            $this->assertLessThanOrEqual(12, count($queries));
            foreach ($queries as $query) {
                if (str_contains($query['query'], 'from "cost_runs"')) {
                    $this->assertStringNotContainsString('"result"', $query['query']);
                }
            }
            $this->assertNotNull($response->json('allocations.data.0.trace'));
            $this->getJson($this->url($trip, $run, $product->id).'?page=2')->assertOk()->assertJsonPath('allocations.current_page', 2);
        }
        $this->assertSame($counts[0], $counts[1]);
        $plan = DB::select('EXPLAIN QUERY PLAN SELECT * FROM cost_allocations WHERE cost_run_id = ? AND product_id = ? ORDER BY expense_id, id LIMIT 20', [$run->id, $product->id]);
        $this->assertStringContainsString('cost_allocations_product_page', json_encode($plan, JSON_THROW_ON_ERROR));
        fwrite(STDOUT, "\nDesglose 20/500 gastos: {$counts[0]}/{$counts[1]} consultas; {$bytes[0]}/{$bytes[1]} bytes; máximo 20 repartos por respuesta\n");
    }

    public function test_historic_names_rates_and_allocations_survive_live_changes_and_deletion(): void
    {
        [$trip, $product, $run] = $this->fixture();
        $url = $this->url($trip, $run, $product->id);
        $original = $this->getJson($url)->assertOk()->json();
        $trip->update(['tipo_cambio' => '1000']);
        $product->update(['nombre' => 'Nombre nuevo', 'applied_rate' => '999']);
        $trip->expenses()->delete();
        $product->delete();
        $this->getJson($url)->assertOk()->assertJsonPath('line.name', 'Sensor original')
            ->assertJsonPath('line.rate', '950.12345678')->assertJsonPath('allocations.data.0.name', 'Gasto original 1');
        $this->assertSame($original, $this->getJson($url)->json());
        $this->assertSame(1, $trip->fresh()->revision);
    }

    public function test_authorization_and_parent_scoping_reject_other_runs_and_unknown_products(): void
    {
        [$trip, $product, $run] = $this->fixture();
        $other = Trip::create(['user_id' => auth()->id(), 'nombre' => 'Otro', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->getJson($this->url($other, $run, $product->id))->assertNotFound();
        $this->getJson($this->url($trip, $run, 999999))->assertNotFound();
        $this->getJson($this->url($trip, $run, $product->id).'?page=0')->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson($this->url($trip, $run, $product->id))->assertForbidden();
        $this->assertSame(1, DB::table('cost_run_views')->count());
    }

    public function test_legacy_backfill_is_resumable_preserves_original_json_and_does_not_invent_residuals(): void
    {
        [$trip, $product, $run] = $this->fixture(1, true);
        [, , $second] = $this->fixture(1, true);
        foreach ([$run, $second] as $old) {
            foreach (['cost_run_views', 'cost_run_lines', 'cost_run_expenses'] as $table) {
                DB::table($table)->where('cost_run_id', $old->id)->delete();
            }
        }
        $this->actingAs($trip->user);
        $original = DB::table('cost_runs')->orderBy('id')->get()->toArray();
        $amounts = DB::table('cost_allocations')->orderBy('id')->get()->toArray();
        $this->getJson($this->url($trip, $run, $product->id))->assertStatus(409);
        $this->assertSame(0, DB::table('cost_run_views')->count());
        $this->artisan('costoreal:cost-read-backfill', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(1, DB::table('cost_run_views')->count());
        $this->getJson($this->url($trip, $run, $product->id))->assertOk()->assertJsonPath('allocations.data.0.trace', null);
        $this->artisan('costoreal:cost-read-backfill')->assertSuccessful();
        $this->artisan('costoreal:cost-read-backfill')->expectsOutput('Lecturas preparadas: 0. Las revisiones y sus importes originales se conservaron.')->assertSuccessful();
        $this->assertSame(2, DB::table('cost_run_views')->count());
        $this->assertEquals($original, DB::table('cost_runs')->orderBy('id')->get()->toArray());
        $this->assertEquals($amounts, DB::table('cost_allocations')->orderBy('id')->get()->toArray());
        $this->assertSame(1, $trip->fresh()->revision);
        $this->artisan('costoreal:cost-read-backfill', ['--limit' => 0])->assertFailed();
    }

    public function test_failed_projection_rolls_back_the_financial_mutation_and_read_marker(): void
    {
        [$trip, $product, $run] = $this->fixture();
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "cost_run_views"')) {
                throw new \RuntimeException('Synthetic projection failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post("/trips/{$trip->id}/expenses", ['concepto' => 'Debe revertirse', 'moneda' => 'CLP', 'monto' => '200', 'expected_revision' => 1]);
            $this->fail('Projection failure expected');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic projection failure', $error->getMessage());
        }
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(1, $trip->costRuns()->count());
        $this->assertSame(1, $trip->expenses()->count());
        $this->assertSame(1, DB::table('cost_run_views')->count());
        $this->assertSame(2, DB::table('cost_run_lines')->count());
    }

    public function test_unassigned_lot_expense_is_explicit_and_not_invented_as_a_product_allocation(): void
    {
        [$trip, $product] = $this->fixture();
        $trip->products()->update(['costo_unitario' => '0']);
        $trip = $trip->fresh();
        $result = app(ProrrateoService::class)->calcularProrrateo($trip);
        $run = app(RecordCostRun::class)->execute($trip, $result, 'unassigned_fixture');
        $this->getJson($this->url($trip, $run, $product->id))->assertOk()->assertJsonPath('line.assigned', '0')
            ->assertJsonPath('line.total', '0')->assertJsonPath('totals.unassigned', '101')->assertJsonPath('totals.total', '101')
            ->assertJsonCount(0, 'allocations.data');
    }

    public function test_recording_itself_is_atomic_when_no_outer_mutation_transaction_exists(): void
    {
        [$trip] = $this->fixture();
        $result = app(ProrrateoService::class)->preview($trip->fresh());
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "cost_run_views"')) {
                throw new \RuntimeException('Synthetic direct recording failure');
            }
        });
        try {
            app(RecordCostRun::class)->execute($trip, $result, 'must_rollback');
            $this->fail('Recording failure expected');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic direct recording failure', $error->getMessage());
        }
        $this->assertSame(1, $trip->costRuns()->count());
        $this->assertSame(1, DB::table('cost_run_views')->count());
        $this->assertSame(2, DB::table('cost_run_lines')->count());
        $this->assertSame(2, DB::table('cost_allocations')->count());
    }
}
