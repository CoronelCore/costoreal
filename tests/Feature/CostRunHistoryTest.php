<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Modules\Reporting\ReportTrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CostRunHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function trip(): Trip
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return Trip::create(['user_id' => $user->id, 'nombre' => 'Historial extenso', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
    }

    private function revisions(): array
    {
        $trip = $this->trip();
        $this->postJson("/trips/{$trip->id}/products", ['expected_revision' => 1, 'nombre' => 'Producto original', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100'])->assertOk();
        $product = $trip->products()->firstOrFail();
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $close = $trip->costRuns()->where('reason', 'close')->firstOrFail();
        $this->patch("/trips/{$trip->id}/reopen", ['expected_revision' => 3, 'reason' => 'Actualizar los precios de prueba'])->assertSessionHasNoErrors();
        for ($i = 0; $i < 60; $i++) {
            $this->patchJson("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['expected_revision' => 4 + $i, 'precio_venta' => (string) (100000 + $i)])->assertRedirect()->assertSessionHasNoErrors();
        }

        return [$trip->fresh(), $close];
    }

    public function test_a_close_older_than_50_runs_is_discoverable_and_reports_original_costs(): void
    {
        [$trip,$close] = $this->revisions();
        $snapshot = $close->snapshot;
        $first = $this->getJson("/trips/{$trip->id}/cost-runs")->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 62)->assertJsonPath('last_page', 4)->json();
        $last = $this->getJson("/trips/{$trip->id}/cost-runs?page=4&anchor={$first['anchor']}")->assertOk()->assertJsonCount(2, 'data')->json();
        $this->assertContains($close->id, array_column($last['data'], 'id'));
        $this->getJson("/trips/{$trip->id}/cost-runs?reason=close")->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $close->id);
        $this->getJson("/trips/{$trip->id}/cost-runs?revision=3")->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $close->id);
        $historical = app(ReportTrip::class)->resolve($trip, $close->id);
        $this->assertSame('90000.00', $historical->products[0]->subtotal_clp);
        $this->assertNull($historical->products[0]->precio_venta);
        $this->get("/trips/{$trip->id}/export/pdf?run={$close->id}")->assertOk();
        $this->get("/trips/{$trip->id}/export/excel?run={$close->id}")->assertOk();
        $this->assertSame($snapshot, $close->fresh()->snapshot);
        $this->assertSame(64, $trip->fresh()->revision);
    }

    public function test_page_anchor_does_not_shift_when_new_runs_arrive(): void
    {
        [$trip,$close] = $this->revisions();
        $first = $this->getJson("/trips/{$trip->id}/cost-runs")->assertOk()->json();
        $anchor = $first['anchor'];
        $before = $this->getJson("/trips/{$trip->id}/cost-runs?page=2&anchor=$anchor")->assertOk()->json('data');
        $product = $trip->products()->firstOrFail();
        $this->patchJson("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['expected_revision' => 64, 'precio_venta' => '150000'])->assertRedirect()->assertSessionHasNoErrors();
        $after = $this->getJson("/trips/{$trip->id}/cost-runs?page=2&anchor=$anchor")->assertOk()->assertJsonPath('total', 62)->json('data');
        $this->assertSame($before, $after);
        $this->getJson("/trips/{$trip->id}/cost-runs")->assertOk()->assertJsonPath('total', 63);
        $this->assertSame(65, $trip->fresh()->revision);
    }

    public function test_foreign_parent_anchor_and_trashed_parent_cannot_be_read(): void
    {
        [$trip,$close] = $this->revisions();
        $owner = User::findOrFail($trip->user_id);
        $foreign = $this->trip();
        $this->getJson("/trips/{$trip->id}/cost-runs")->assertForbidden();
        $this->actingAs($owner)->getJson("/trips/{$foreign->id}/cost-runs?anchor={$close->id}")->assertForbidden();
        $this->getJson("/trips/{$trip->id}/cost-runs?anchor=999999")->assertNotFound();
        $other = Trip::create(['user_id' => $owner->id, 'nombre' => 'Otro', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => 900, 'estado' => 'activo']);
        $this->getJson("/trips/{$other->id}/cost-runs?anchor={$close->id}")->assertNotFound();
        $trip->delete();
        $this->getJson("/trips/{$trip->id}/cost-runs")->assertNotFound();
    }

    public function test_empty_and_invalid_filters_have_explicit_contracts(): void
    {
        $trip = $this->trip();
        $this->getJson("/trips/{$trip->id}/cost-runs")->assertOk()->assertJsonPath('data', [])->assertJsonPath('anchor', null)->assertJsonPath('total', 0);
        foreach (['page' => 0, 'anchor' => 0, 'reason' => 'reopen', 'revision' => 0] as $key => $value) {
            $this->getJson("/trips/{$trip->id}/cost-runs?$key=$value")->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_metadata_read_and_initial_detail_are_bounded_and_never_load_snapshots(): void
    {
        [$trip,$close] = $this->revisions();
        $before = $trip->costRuns()->get()->toJson();
        DB::enableQueryLog();
        $data = $this->getJson("/trips/{$trip->id}/cost-runs")->assertOk();
        $sql = implode('\n', array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['input', 'snapshot', 'result', 'input_hash'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $sql);
            $this->assertArrayNotHasKey($field, $data->json('data.0'));
        }
        $this->assertLessThan(7000, strlen($data->getContent()));
        $initial = $this->get("/trips/{$trip->id}")->assertOk()->viewData('page')['props'];
        $this->assertArrayNotHasKey('costRuns', $initial);
        $this->assertSame(64, $initial['latestCostRun']['revision']);
        $this->assertSame($before, $trip->costRuns()->get()->toJson());
    }
}
