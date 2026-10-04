<?php

namespace Tests\Feature;

use App\Exports\TripSummarySheet;
use App\Models\CostReconciliation;
use App\Models\CostRun;
use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Costing\Domain\CostResult;
use App\Modules\Costing\Reconciliation\CompareStoredCosts;
use App\Modules\Reporting\ReportTrip;
use App\Services\ProrrateoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CostReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function legacy(string $state = 'activo'): Trip
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Costo heredado QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '1000', 'estado' => $state]);
        $trip->products()->create(['nombre' => 'Producto original', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100', 'subtotal_clp' => '99000', 'gastos_prorrateados_clp' => '10000', 'costo_final_total_clp' => '109000', 'costo_final_unitario_clp' => '109000', 'precio_venta' => '150000']);
        $trip->expenses()->create(['concepto' => 'Gasto original', 'moneda' => 'USD', 'monto' => '10', 'monto_clp' => '10000']);

        return $trip->fresh();
    }

    private function save(Trip $trip): array
    {
        $data = $this->getJson("/trips/{$trip->id}/cost-comparisons/preview")->assertOk()->json();

        return $this->postJson("/trips/{$trip->id}/cost-comparisons", ['expected_revision' => $data['revision'], 'fingerprint' => $data['fingerprint']])->assertOk()->json();
    }

    private function apply(Trip $trip, array $saved, ?array $keys = null)
    {
        return $this->postJson("/trips/{$trip->id}/cost-comparisons/{$saved['id']}/apply", ['expected_revision' => $saved['revision'], 'acknowledged_keys' => $keys ?? array_column($saved['differences']['data'], 'key')]);
    }

    public function test_preview_compares_actual_stored_amounts_and_never_writes(): void
    {
        $trip = $this->legacy();
        $before = app(CompareStoredCosts::class)->snapshot($trip);
        $data = $this->getJson("/trips/{$trip->id}/cost-comparisons/preview")->assertOk()->assertJsonPath('before.total', '109000.00')->assertJsonPath('after.total', '110000')->assertJsonPath('warnings_count', 2)->assertJsonPath('differences.total', 3)->assertJsonMissingPath('snapshot')->assertJsonMissingPath('allocations')->json();
        $this->assertSame('conversion', $data['differences']['data'][0]['classification']);
        $this->assertSame('1000.00', $data['differences']['data'][0]['delta']);
        $this->assertSame($before, app(CompareStoredCosts::class)->snapshot($trip->fresh()));
        $this->assertSame(0, CostReconciliation::count());
        $this->assertSame(0, LegacyCostBaseline::count());
        $this->assertSame(0, CostRun::count());
        $this->assertSame(0, $trip->auditEvents()->count());
    }

    public function test_save_is_reentrant_and_apply_preserves_originals_and_actor(): void
    {
        $trip = $this->legacy();
        $saved = $this->save($trip);
        $repeat = $this->save($trip);
        $this->assertSame($saved['id'], $repeat['id']);
        $this->assertSame(1, CostReconciliation::count());
        $this->assertSame(1, LegacyCostBaseline::count());
        $this->assertSame(1, $trip->fresh()->revision);
        $baseline = LegacyCostBaseline::firstOrFail();
        $comparison = CostReconciliation::firstOrFail()->comparison;
        $this->apply($trip, $saved)->assertRedirect();
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame('110000.00', $trip->products()->first()->costo_final_total_clp);
        $this->assertSame('150000.00', $trip->products()->first()->precio_venta);
        $this->assertSame('legacy-unverified', $trip->products()->first()->rate_source);
        $this->assertNull($trip->products()->first()->rate_date);
        $this->assertSame($comparison, CostReconciliation::firstOrFail()->comparison);
        $this->assertSame(2, CostReconciliation::firstOrFail()->applied_revision);
        $audit = $trip->auditEvents()->firstOrFail();
        $this->assertSame('cost_reconcile', $audit->action);
        $this->assertSame(auth()->id(), $audit->actor_id);
        $this->assertSame('109000.00', $audit->before['products'][0]['costo_final_total_clp']);
        $this->assertSame('110000.00', $audit->after['products'][0]['costo_final_total_clp']);
        $this->assertSame('largest-remainder-clp-v1', $trip->costRuns()->first()->algorithm);
        $original = app(ReportTrip::class)->resolve($trip->fresh(), baselineId: $baseline->id);
        $this->assertSame('109000.00', $original->products[0]->costo_final_total_clp);
        $this->assertStringContainsString('sin verificar', $original->report_provenance);
        $sheet = (new TripSummarySheet($original))->array();
        $this->assertStringContainsString('Copia heredada', end($sheet)[1]);
        $this->get("/trips/{$trip->id}/export/pdf?baseline={$baseline->id}")->assertOk();
        $this->get("/trips/{$trip->id}/export/excel?baseline={$baseline->id}")->assertOk();
        $this->apply($trip, $saved)->assertRedirect();
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame(1, $trip->costRuns()->count());
    }

    public function test_each_difference_requires_review_and_extra_keys_are_rejected(): void
    {
        $trip = $this->legacy();
        $saved = $this->save($trip);
        $keys = array_column($saved['differences']['data'], 'key');
        $this->apply($trip, $saved, [$keys[0]])->assertUnprocessable()->assertJsonValidationErrors('acknowledged_keys');
        $this->apply($trip, $saved, [...$keys, 'products:999:subtotal_clp'])->assertUnprocessable();
        $this->apply($trip, $saved, [...$keys, $keys[0]])->assertUnprocessable();
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertNull(CostReconciliation::first()->applied_revision);
        $this->assertSame(0, $trip->costRuns()->count());
    }

    public function test_stale_revisions_and_out_of_band_amount_changes_are_rejected(): void
    {
        $trip = $this->legacy();
        $saved = $this->save($trip);
        $trip->products()->update(['subtotal_clp' => '98000']);
        $this->apply($trip, $saved)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->postJson("/trips/{$trip->id}/cost-comparisons", ['expected_revision' => 1, 'fingerprint' => $saved['fingerprint']])->assertUnprocessable()->assertJsonValidationErrors('conflict');
        DB::table('trips')->where('id', $trip->id)->update(['revision' => 2]);
        $this->apply($trip, $saved)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->assertSame('98000.00', $trip->products()->first()->subtotal_clp);
        $this->assertSame(0, $trip->costRuns()->count());
        $this->assertNull(CostReconciliation::first()->applied_at);
    }

    public function test_closed_legacy_reopen_preserves_amounts_and_original_close(): void
    {
        $trip = $this->legacy('finalizado');
        $saved = $this->save($trip);
        $this->apply($trip, $saved)->assertForbidden();
        $this->patchJson("/trips/{$trip->id}/reopen", ['reason' => 'Corregir una nueva operación documentada', 'expected_revision' => 1])->assertRedirect();
        $this->assertSame('activo', $trip->fresh()->estado);
        $this->assertSame('109000.00', $trip->products()->first()->costo_final_total_clp);
        $this->assertSame(0, $trip->costRuns()->count());
        $baseline = LegacyCostBaseline::firstOrFail();
        $this->assertSame('finalizado', $baseline->snapshot['estado']);
        $this->apply($trip, $saved)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $new = $this->save($trip);
        $this->apply($trip, $new)->assertRedirect();
        $this->assertSame(3, $trip->fresh()->revision);
        $original = app(ReportTrip::class)->resolve($trip->fresh(), baselineId: $baseline->id);
        $this->assertSame('finalizado', $original->estado);
        $this->assertSame('109000.00', $original->products[0]->costo_final_total_clp);
        $this->assertSame(2, CostReconciliation::count());
    }

    public function test_unknown_currency_and_missing_paid_evidence_block_without_invention(): void
    {
        $trip = $this->legacy();
        $trip->products()->update(['rate_mode' => 'paid', 'applied_rate' => '950', 'rate_source' => '', 'rate_date' => null]);
        $saved = $this->save($trip);
        $this->assertNotEmpty($saved['errors']);
        $this->assertSame('109000.00', $saved['before']['total']);
        $this->assertNull($saved['after']);
        $this->apply($trip, $saved, ['dummy'])->assertUnprocessable()->assertJsonValidationErrors('comparison');
        $trip->products()->update(['moneda' => 'XYZ', 'rate_mode' => 'estimated']);
        $this->getJson("/trips/{$trip->id}/cost-comparisons/preview")->assertOk()->assertJsonCount(1, 'errors')->assertJsonPath('after', null);
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame('', $trip->products()->first()->rate_source);
        $this->assertSame(0, $trip->costRuns()->count());
    }

    public function test_rounding_is_classified_only_with_numeric_proof_and_inconsistency_is_visible(): void
    {
        $trip = $this->legacy();
        $trip->products()->update(['subtotal_clp' => '99999.75', 'costo_final_total_clp' => '109998.75']);
        $data = $this->getJson("/trips/{$trip->id}/cost-comparisons/preview")->assertOk()->json('differences.data');
        $this->assertSame('clp_rounding', $data[0]['classification']);
        $this->assertSame('stored_inconsistency', collect($data)->firstWhere('field', 'costo_final_total_clp')['classification']);
    }

    public function test_backfill_batches_resume_and_repeat_without_rewriting_history(): void
    {
        $first = $this->legacy();
        $second = $this->legacy('finalizado');
        $before = DB::table('products')->get()->toJson();
        $this->artisan('costoreal:legacy-cost-review', ['--limit' => 1])->expectsOutputToContain('checkpoint: '.$first->id)->assertSuccessful();
        $this->assertSame(1, CostReconciliation::count());
        $this->artisan('costoreal:legacy-cost-review', ['--after-id' => $first->id, '--limit' => 1])->assertSuccessful();
        $this->artisan('costoreal:legacy-cost-review')->expectsOutputToContain('comparaciones nuevas: 0')->assertSuccessful();
        $this->assertSame(2, CostReconciliation::count());
        $this->assertSame(2, LegacyCostBaseline::count());
        $this->assertSame($before, DB::table('products')->get()->toJson());
        $this->assertSame(1, $first->fresh()->revision);
        $this->assertSame(1, $second->fresh()->revision);
        $this->assertSame(0, CostRun::count());
        $this->artisan('costoreal:legacy-cost-review', ['--limit' => 0])->assertFailed();
    }

    public function test_new_policy_can_save_a_fresh_review_at_the_same_revision(): void
    {
        $trip = $this->legacy();
        $result = app(ProrrateoService::class)->preview($trip->fresh());
        $newResult = new CostResult($result->lines, $result->allocations, $result->purchase, $result->expenses, $result->assigned, $result->unassigned, $result->total, 'comparison-policy-v2');
        $advanced = false;
        $this->partialMock(ProrrateoService::class, function ($mock) use ($result, $newResult, &$advanced) {
            $mock->shouldReceive('preview')->andReturnUsing(function () use ($result, $newResult, &$advanced) {
                return $advanced ? $newResult : $result;
            });
        });
        $old = $this->save($trip);
        $advanced = true;
        $this->apply($trip, $old)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $new = $this->save($trip);
        $this->assertNotSame($old['fingerprint'], $new['fingerprint']);
        $this->assertNotSame($old['id'], $new['id']);
        $this->assertSame(2, CostReconciliation::count());
        $this->assertSame(1, LegacyCostBaseline::count());
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame('largest-remainder-clp-v1', CostReconciliation::find($old['id'])->comparison['algorithm']);
    }

    public function test_first_ordinary_mutation_preserves_legacy_values_before_recalculation(): void
    {
        $trip = $this->legacy();
        $product = $trip->products()->firstOrFail();
        $this->patchJson("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['expected_revision' => 1, 'precio_venta' => '170000'])->assertRedirect();
        $baseline = LegacyCostBaseline::firstOrFail();
        $this->assertSame('109000.00', $baseline->snapshot['products'][0]['costo_final_total_clp']);
        $this->assertSame('150000.00', $baseline->snapshot['products'][0]['precio_venta']);
        $this->assertSame(1, $baseline->revision);
        $this->assertSame('110000.00', $product->fresh()->costo_final_total_clp);
        $this->assertSame('170000.00', $product->fresh()->precio_venta);
        $this->assertSame(2, $trip->fresh()->revision);
    }

    public function test_comparison_and_baseline_are_immutable(): void
    {
        $trip = $this->legacy();
        $this->save($trip);
        foreach ([CostReconciliation::first(), LegacyCostBaseline::first()] as $record) {
            try {
                $record->update(['snapshot' => []]);
                $this->fail('Expected immutable record');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('inmutable', $e->getMessage());
            }
        }
    }

    public function test_scoped_access_pagination_and_private_snapshot_contract(): void
    {
        $trip = $this->legacy();
        $saved = $this->save($trip);
        $other = Trip::create(['user_id' => auth()->id(), 'nombre' => 'Otra', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '1000', 'estado' => 'activo']);
        $this->getJson("/trips/{$other->id}/cost-comparisons/{$saved['id']}")->assertNotFound();
        $this->getJson("/trips/{$other->id}/export/pdf?baseline=".LegacyCostBaseline::first()->id)->assertNotFound();
        $this->getJson("/trips/{$trip->id}/export/pdf?baseline=1&run=1")->assertUnprocessable();
        $this->getJson("/trips/{$trip->id}/cost-comparisons/preview?page=0")->assertUnprocessable();
        $this->getJson("/trips/{$trip->id}/cost-comparisons/{$saved['id']}")->assertOk()->assertJsonMissingPath('snapshot')->assertJsonMissingPath('actor.email');
        $this->actingAs(User::factory()->create())->getJson("/trips/{$trip->id}/cost-comparisons/{$saved['id']}")->assertForbidden();
        $this->postJson("/trips/{$trip->id}/cost-comparisons", ['expected_revision' => 1, 'fingerprint' => $saved['fingerprint']])->assertForbidden();
    }

    public function test_comparison_paginates_differences_and_bounds_response(): void
    {
        $trip = $this->legacy();
        for ($i = 0; $i < 25; $i++) {
            $trip->products()->create(['nombre' => 'Línea '.$i, 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => 1, 'subtotal_clp' => 0, 'costo_final_total_clp' => 0, 'costo_final_unitario_clp' => 0]);
        }
        $response = $this->getJson("/trips/{$trip->id}/cost-comparisons/preview")->assertOk()->assertJsonCount(20, 'differences.data');
        $this->assertGreaterThan(20, $response->json('differences.total'));
        $this->assertLessThan(16000, strlen($response->getContent()));
        $this->getJson("/trips/{$trip->id}/cost-comparisons/preview?page=2")->assertOk()->assertJsonPath('differences.current_page', 2)->assertJsonCount(20, 'differences.data');
    }

    public function test_failure_after_recalculation_rolls_back_review_status_costs_and_revision(): void
    {
        $trip = $this->legacy();
        $saved = $this->save($trip);
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "cost_run_views"')) {
                throw new \RuntimeException('QA failure after costs');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->apply($trip, $saved);
            $this->fail('Expected injected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('QA failure after costs', $e->getMessage());
        }
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame('109000.00', $trip->products()->first()->costo_final_total_clp);
        $this->assertNull(CostReconciliation::first()->applied_at);
        $this->assertSame(0, CostRun::count());
        $this->assertSame(0, $trip->auditEvents()->count());
        $this->assertSame(1, LegacyCostBaseline::count());
    }
}
