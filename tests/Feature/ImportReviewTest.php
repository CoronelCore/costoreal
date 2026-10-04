<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CostRun;
use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportReviewTest extends TestCase
{
    use RefreshDatabase;

    private function trip(): Trip
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        return Trip::create(['user_id' => $owner->id, 'nombre' => 'Revisión de cierre', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '1000', 'estado' => 'activo']);
    }

    private function coherent(): Trip
    {
        $trip = $this->trip();
        $this->postJson("/trips/{$trip->id}/products", ['expected_revision' => 1, 'nombre' => 'Compra estimada', 'moneda' => 'USD', 'cantidad' => 3, 'costo_unitario' => '100.25'])->assertOk();
        $this->postJson("/trips/{$trip->id}/expenses", ['expected_revision' => 2, 'concepto' => 'Despacho', 'moneda' => 'CLP', 'monto' => '20', 'allocation_method' => 'units'])->assertOk();

        return $trip->fresh();
    }

    public function test_review_is_read_only_and_distinguishes_optional_warnings(): void
    {
        $trip = $this->coherent();
        $before = $trip->products()->first()->getAttributes();
        $runs = CostRun::count();
        $events = AuditEvent::count();
        $data = $this->getJson("/trips/{$trip->id}/review?expected_revision=3")->assertOk()->assertJsonPath('revision', 3)->assertJsonPath('can_close', true)->assertJsonPath('stored_total', '300770.00')->assertJsonPath('calculated_total', '300770')->assertJsonPath('blocks', [])->assertJsonPath('document_policy', 'optional')->assertJsonCount(3, 'warnings')->json();
        $this->assertStringContainsString('precios pendientes', $data['warnings'][0]);
        $this->assertStringContainsString('operaciones estimadas', $data['warnings'][1]);
        $this->assertStringContainsString('comprobante', $data['warnings'][2]);
        $this->assertSame($before, $trip->products()->first()->getAttributes());
        $this->assertSame($runs, CostRun::count());
        $this->assertSame($events, AuditEvent::count());
        $this->assertSame(0, LegacyCostBaseline::count());
        $this->assertStringNotContainsString('storage_path', json_encode($data));
    }

    public function test_legacy_mismatch_blocks_close_until_explicit_reconciliation(): void
    {
        $trip = $this->trip();
        $p = $trip->products()->create(['nombre' => 'Original', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100', 'subtotal_clp' => 99000, 'gastos_prorrateados_clp' => 0, 'costo_final_total_clp' => 99000, 'costo_final_unitario_clp' => 99000]);
        $before = $p->fresh()->getAttributes();
        $this->getJson("/trips/{$trip->id}/review")->assertOk()->assertJsonPath('can_close', false)->assertJsonPath('differences_count', 3);
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('cierre');
        $this->assertSame($before, $p->fresh()->getAttributes());
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame('activo', $trip->fresh()->estado);
        $this->assertSame(0, CostRun::count());
        $this->assertSame(0, AuditEvent::count());
        $this->assertSame(0, LegacyCostBaseline::count());
        $preview = $this->getJson("/trips/{$trip->id}/cost-comparisons/preview")->assertOk()->json();
        $saved = $this->postJson("/trips/{$trip->id}/cost-comparisons", ['expected_revision' => 1, 'fingerprint' => $preview['fingerprint']])->assertOk()->json();
        $this->postJson("/trips/{$trip->id}/cost-comparisons/{$saved['id']}/apply", ['expected_revision' => 1, 'acknowledged_keys' => array_column($saved['differences']['data'], 'key')])->assertRedirect();
        $this->getJson("/trips/{$trip->id}/review")->assertOk()->assertJsonPath('can_close', true);
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 2])->assertRedirect();
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertSame('99000.00', LegacyCostBaseline::first()->snapshot['products'][0]['subtotal_clp']);
        $this->assertSame('100000.00', $p->fresh()->subtotal_clp);
    }

    public function test_bad_rate_or_distribution_blocks_review_and_direct_close(): void
    {
        $trip = $this->coherent();
        DB::table('trips')->where('id', $trip->id)->update(['tipo_cambio' => '0']);
        $this->getJson("/trips/{$trip->id}/review")->assertOk()->assertJsonPath('can_close', false);
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 3])->assertUnprocessable()->assertJsonValidationErrors('cierre');
        $this->assertSame(3, $trip->fresh()->revision);
        DB::table('trips')->where('id', $trip->id)->update(['tipo_cambio' => '1000']);
        DB::table('expenses')->where('trip_id', $trip->id)->update(['allocation_method' => 'weight']);
        $data = $this->getJson("/trips/{$trip->id}/review")->assertOk()->assertJsonPath('can_close', false)->json();
        $this->assertNotEmpty($data['blocks']);
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 3])->assertUnprocessable()->assertJsonValidationErrors('cierre');
        $this->assertSame(2, CostRun::count());
    }

    public function test_paid_clp_cannot_bypass_evidence_or_rate_one_requirement(): void
    {
        $trip = $this->coherent();
        DB::table('expenses')->where('trip_id', $trip->id)->update(['rate_mode' => 'paid', 'applied_rate' => '2', 'rate_source' => 'legacy-unverified', 'rate_date' => '2026-99-99']);
        $response = $this->getJson("/trips/{$trip->id}/review")->assertOk()->assertJsonPath('can_close', false)->json();
        $this->assertGreaterThanOrEqual(3, count($response['blocks']));
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 3])->assertUnprocessable()->assertJsonValidationErrors('cierre');
        $this->assertSame('activo', $trip->fresh()->estado);
    }

    public function test_review_rejects_foreign_user_and_stale_revision(): void
    {
        $trip = $this->coherent();
        $this->getJson("/trips/{$trip->id}/review?expected_revision=2")->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->actingAs(User::factory()->create())->getJson("/trips/{$trip->id}/review")->assertForbidden();
        $this->assertSame(3, $trip->fresh()->revision);
    }

    public function test_reopen_requires_reason_and_preserves_close_and_costs(): void
    {
        $trip = $this->coherent();
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 3])->assertRedirect();
        $run = CostRun::where('reason', 'close')->firstOrFail();
        $snapshot = $run->snapshot;
        $product = $trip->products()->first()->getAttributes();
        foreach ([null, ' ', 'x', str_repeat('a', 1001)] as $reason) {
            $this->patchJson("/trips/{$trip->id}/reopen", ['expected_revision' => 4, 'reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->assertSame(4, $trip->fresh()->revision);
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertSame(3, CostRun::count());
        $this->patchJson("/trips/{$trip->id}/reopen", ['expected_revision' => 4, 'reason' => '  Incorporar el flete definitivo  '])->assertRedirect();
        $event = AuditEvent::where('action', 'reopen')->firstOrFail();
        $this->assertSame('Incorporar el flete definitivo', $event->reason);
        $this->assertSame($trip->user_id, $event->actor_id);
        $this->assertSame(5, $event->revision);
        $this->assertSame(5, $trip->fresh()->revision);
        $this->assertSame('activo', $trip->fresh()->estado);
        $this->assertSame(3, CostRun::count());
        $this->assertSame($snapshot, $run->fresh()->snapshot);
        $this->assertSame($product, $trip->products()->first()->getAttributes());
        $this->getJson("/trips/{$trip->id}/audit-events")->assertOk()->assertJsonPath('data.0.reason', 'Incorporar el flete definitivo');
        $this->getJson("/trips/{$trip->id}/audit-events/{$event->id}")->assertOk()->assertJsonPath('reason', $event->reason);
        $this->get("/trips/{$trip->id}/export/pdf?run={$run->id}")->assertOk();
    }

    public function test_reopen_conflict_and_failure_cannot_leave_an_unrecorded_state(): void
    {
        $trip = $this->coherent();
        $this->patchJson("/trips/{$trip->id}/close", ['expected_revision' => 3])->assertRedirect();
        $count = AuditEvent::count();
        $this->patchJson("/trips/{$trip->id}/reopen", ['expected_revision' => 3, 'reason' => 'Corregir cantidad'])->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->assertSame($count, AuditEvent::count());
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "audit_events"') && in_array('Synthetic reopen failure', $query->bindings, true)) {
                throw new \RuntimeException('Synthetic reopen failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->patchJson("/trips/{$trip->id}/reopen", ['expected_revision' => 4, 'reason' => 'Synthetic reopen failure']);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic reopen failure', $e->getMessage());
        }
        $this->assertSame(4, $trip->fresh()->revision);
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertSame($count, AuditEvent::count());
        $this->assertSame(3, CostRun::count());
    }
}
