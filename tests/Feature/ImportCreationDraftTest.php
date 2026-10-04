<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ImportDraft;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Importations\Creation\DraftPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportCreationDraftTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        return ['nombre' => 'Alta de prueba', 'descripcion' => '', 'company_id' => null, 'fecha_inicio' => '2026-10-04', 'fecha_fin' => '', 'moneda_trabajo' => 'EUR', 'tipo_cambio' => '950.12345678'];
    }

    private function createDraft(?array $context = null): array
    {
        if (! auth()->user()) {
            $this->actingAs(User::factory()->create());
        }
        $body = ['draft_id' => (string) Str::uuid(), 'write_key' => (string) Str::uuid(), 'payload' => ['context' => $context ?? $this->context(), 'product' => null, 'expense' => null]];

        return [$this->postJson('/import-drafts', $body)->assertOk()->json('draft'), $body];
    }

    private function launch(array $draft, string $mode = 'guided')
    {
        return $this->postJson("/import-drafts/{$draft['id']}/launch", [...$draft['payload']['context'], 'draft_revision' => $draft['revision'], 'mode' => $mode]);
    }

    private function update(array $draft, array $changes = [])
    {
        return $this->putJson("/import-drafts/{$draft['id']}", ['expected_revision' => $draft['revision'], 'write_key' => (string) Str::uuid(), 'step' => $draft['step'], 'payload' => $draft['payload'], ...$changes]);
    }

    public function test_partial_context_is_saved_early_without_creating_financial_rows(): void
    {
        [$draft] = $this->createDraft([...$this->context(), 'nombre' => '', 'tipo_cambio' => '950.1234567']);
        $this->assertSame(1, $draft['revision']);
        $this->assertSame('950.1234567', $draft['payload']['context']['tipo_cambio']);
        $this->assertNull($draft['trip_id']);
        $this->assertSame(0, Trip::count());
        $this->assertSame(0, DB::table('cost_runs')->count());
        $this->get('/trips/create?draft='.$draft['id'])->assertOk()->assertInertia(fn (Assert $page) => $page->component('Trips/Create')->where('draft.id', $draft['id'])->where('draft.revision', 1)->where('trip', null));
    }

    public function test_retries_do_not_duplicate_creation_or_updates_and_stale_tabs_cannot_overwrite(): void
    {
        [$draft, $body] = $this->createDraft();
        $this->postJson('/import-drafts', $body)->assertOk()->assertJsonPath('draft.id', $draft['id']);
        $update = ['expected_revision' => 1, 'write_key' => (string) Str::uuid(), 'step' => 'contexto', 'payload' => [...$draft['payload'], 'context' => [...$this->context(), 'nombre' => 'Último guardado']]];
        $this->putJson("/import-drafts/{$draft['id']}", $update)->assertOk()->assertJsonPath('draft.revision', 2);
        $this->putJson("/import-drafts/{$draft['id']}", $update)->assertOk()->assertJsonPath('draft.revision', 2);
        $this->update($draft)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->assertSame('Último guardado', ImportDraft::first()->payload['context']['nombre']);
        $this->assertSame(1, ImportDraft::count());
        $this->assertSame(0, Trip::count());
    }

    public function test_context_launch_uses_trip_validation_and_is_idempotent(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect('/trips/create?draft='.$draft['id']);
        $this->launch($draft)->assertRedirect();
        $this->assertSame(1, Trip::count());
        $trip = Trip::firstOrFail();
        $this->assertSame('950.12345678', $trip->tipo_cambio);
        $this->assertSame('EUR', $trip->moneda_trabajo);
        $this->assertSame('CLP', $trip->moneda_base);
        $this->assertSame(auth()->id(), $trip->user_id);
        $this->assertSame('productos', ImportDraft::first()->step);
        $this->assertSame(2, ImportDraft::first()->revision);
        $changed = [...$draft, 'payload' => [...$draft['payload'], 'context' => [...$draft['payload']['context'], 'tipo_cambio' => '999']]];
        $this->launch($changed)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->get('/trips/create?draft='.$draft['id'])->assertInertia(fn (Assert $page) => $page->where('trip.id', $trip->id)->where('trip.tipo_cambio', '950.12345678')->where('trip.costo_total', '0'));
    }

    public function test_incomplete_invalid_context_is_preserved_but_cannot_launch(): void
    {
        [$draft] = $this->createDraft([...$this->context(), 'tipo_cambio' => '0', 'fecha_fin' => '2026-10-01']);
        $this->launch($draft)->assertUnprocessable()->assertJsonValidationErrors(['tipo_cambio', 'fecha_fin']);
        $this->assertSame('0', ImportDraft::first()->payload['context']['tipo_cambio']);
        $this->assertSame(0, Trip::count());
        $this->update($draft, ['step' => 'productos'])->assertUnprocessable()->assertJsonValidationErrors('step');
    }

    public function test_saved_editors_preserve_decimal_text_and_source_revision_without_changing_costs(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $draft = app(DraftPayload::class)->data(ImportDraft::first());
        $payload = [...$draft['payload'], 'product' => ['mode' => 'add', 'id' => null, 'base' => null, 'fields' => ['nombre' => 'Sensor pendiente', 'moneda' => 'EUR', 'cantidad' => '3', 'costo_unitario' => '105.25', 'weight' => '2.12345678', 'volume' => '', 'rate_mode' => 'paid', 'applied_rate' => '940.12345678', 'rate_source' => 'Comprobante', 'rate_date' => '2026-10-04', 'expected_revision' => 1]]];
        $this->update($draft, ['payload' => $payload])->assertOk()->assertJsonPath('draft.payload.product.fields.applied_rate', '940.12345678')->assertJsonPath('draft.payload.product.fields.weight', '2.12345678');
        $this->assertSame(0, Trip::first()->products()->count());
        $this->assertSame(1, Trip::first()->revision);
        $this->get('/trips/create?draft='.$draft['id'])->assertInertia(fn (Assert $page) => $page->where('draft.payload.product.fields.costo_unitario', '105.25')->where('draft.payload.product.fields.expected_revision', 1)->where('trip.products', []));
    }

    public function test_guide_reuses_product_expense_actions_and_can_finish_with_exact_closed_snapshot(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $trip = Trip::firstOrFail();
        $this->postJson("/trips/{$trip->id}/products", ['expected_revision' => 1, 'nombre' => 'Compra', 'moneda' => 'EUR', 'cantidad' => 3, 'costo_unitario' => '100'])->assertOk()->assertJsonPath('revision', 2);
        $this->postJson("/trips/{$trip->id}/expenses", ['expected_revision' => 2, 'concepto' => 'Despacho', 'moneda' => 'CLP', 'monto' => '10000'])->assertOk()->assertJsonPath('revision', 3);
        $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 2, 'expected_revision' => 3, 'mode' => 'close'])->assertRedirect();
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertSame(4, $trip->fresh()->revision);
        $this->assertSame('295037', $trip->costRuns()->where('reason', 'close')->first()->result['total']);
        $this->assertNotNull(ImportDraft::first()->completed_at);
        $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 2, 'expected_revision' => 3, 'mode' => 'close'])->assertRedirect();
        $this->assertSame(1, $trip->costRuns()->where('reason', 'close')->count());
        $this->get('/trips/create?draft='.$draft['id'])->assertRedirect('/trips/'.$trip->id);
    }

    public function test_failed_close_rolls_back_draft_completion_and_trip_revision(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $trip = Trip::first();
        $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 2, 'expected_revision' => 1, 'mode' => 'close'])->assertUnprocessable()->assertJsonValidationErrors('cierre');
        $this->assertNull(ImportDraft::first()->completed_at);
        $this->assertSame(2, ImportDraft::first()->revision);
        $this->assertSame('activo', $trip->fresh()->estado);
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, $trip->costRuns()->count());
    }

    public function test_finish_active_and_quick_creation_do_not_require_closing(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 2, 'expected_revision' => 1, 'mode' => 'active'])->assertRedirect();
        $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 2, 'expected_revision' => 1, 'mode' => 'close'])->assertRedirect()->assertSessionHas('success', 'Alta completada. La importación queda en curso para seguir trabajando.');
        $this->assertSame('activo', Trip::first()->estado);
        $this->assertSame(1, Trip::first()->revision);
        [$quick] = $this->createDraft([...$this->context(), 'nombre' => 'Operador frecuente']);
        $this->launch($quick, 'quick')->assertRedirect('/trips/2?section=productos#productos');
        $this->assertNotNull(ImportDraft::find($quick['id'])->completed_at);
        $this->get('/trips/2?section=productos')->assertInertia(fn (Assert $page) => $page->where('initialTab', 'productos'));
        $this->getJson('/trips/2?section=invalid')->assertUnprocessable()->assertJsonValidationErrors('section');
        $this->assertSame(2, Trip::count());
    }

    public function test_foreign_drafts_company_and_children_are_rejected_without_writes(): void
    {
        [$draft] = $this->createDraft();
        $owner = auth()->user();
        $this->launch($draft)->assertRedirect();
        $foreign = User::factory()->create();
        $this->actingAs($foreign)->get('/trips/create?draft='.$draft['id'])->assertNotFound();
        $this->update($draft)->assertForbidden();
        $this->launch($draft)->assertForbidden();
        $this->actingAs($owner);
        $company = Company::create(['user_id' => $foreign->id, 'nombre' => 'Ajena', 'activa' => true]);
        $this->update($draft, ['payload' => [...$draft['payload'], 'context' => [...$this->context(), 'company_id' => $company->id]]])->assertUnprocessable()->assertJsonValidationErrors('payload.context.company_id');
        $this->assertSame(2, ImportDraft::first()->revision);
        $this->assertSame(1, Trip::count());
    }

    public function test_pending_editor_cannot_be_discarded_by_finishing_the_guide(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $draft = app(DraftPayload::class)->data(ImportDraft::first());
        $payload = [...$draft['payload'], 'expense' => ['id' => null, 'receipt_name' => 'pendiente.pdf', 'fields' => ['concepto' => 'Pendiente', 'moneda' => 'EUR', 'monto' => '10.25', 'expected_revision' => 1]]];
        $this->update($draft, ['payload' => $payload])->assertOk();
        $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 3, 'expected_revision' => 1, 'mode' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('draft');
        $this->assertNull(ImportDraft::first()->completed_at);
        $this->assertSame('10.25', ImportDraft::first()->payload['expense']['fields']['monto']);
        $this->assertSame(1, Trip::first()->revision);
    }

    public function test_foreign_product_cannot_be_embedded_in_an_owned_draft(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $draft = app(DraftPayload::class)->data(ImportDraft::first());
        $other = Trip::create(['user_id' => auth()->id(), 'nombre' => 'Otro lote', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'EUR', 'tipo_cambio' => '1000', 'estado' => 'activo']);
        $product = $other->products()->create(['nombre' => 'Producto de otro lote', 'moneda' => 'EUR', 'cantidad' => 1, 'costo_unitario' => 100, 'subtotal_clp' => 100000, 'costo_final_total_clp' => 100000, 'costo_final_unitario_clp' => '100000']);
        $payload = [...$draft['payload'], 'product' => ['mode' => 'edit', 'id' => $product->id, 'fields' => ['nombre' => 'Ajeno', 'expected_revision' => 1], 'base' => ['nombre' => 'Original']]];
        $this->update($draft, ['payload' => $payload])->assertUnprocessable()->assertJsonValidationErrors('payload.product.id');
        $this->assertNull(ImportDraft::first()->payload['product']);
        $this->assertSame('Producto de otro lote', $product->fresh()->nombre);
        $this->assertSame(1, $other->fresh()->revision);
    }

    public function test_completion_failure_rolls_back_the_close_and_its_history(): void
    {
        [$draft] = $this->createDraft();
        $this->launch($draft)->assertRedirect();
        $trip = Trip::first();
        $this->postJson("/trips/{$trip->id}/products", ['expected_revision' => 1, 'nombre' => 'Compra', 'moneda' => 'EUR', 'cantidad' => 3, 'costo_unitario' => '100'])->assertOk();
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'update "import_drafts"') && str_contains($query->sql, '"completed_at"')) {
                throw new \RuntimeException('Synthetic completion failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson("/import-drafts/{$draft['id']}/finish", ['draft_revision' => 2, 'expected_revision' => 2, 'mode' => 'close']);
            $this->fail('Expected completion failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic completion failure', $e->getMessage());
        }
        $this->assertNull(ImportDraft::first()->completed_at);
        $this->assertSame(2, ImportDraft::first()->revision);
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame('activo', $trip->fresh()->estado);
        $this->assertSame(0, $trip->costRuns()->where('reason', 'close')->count());
        $this->assertSame(1, $trip->auditEvents()->count());
    }

    public function test_unknown_snapshot_fields_are_rejected_and_listing_is_private(): void
    {
        [$draft] = $this->createDraft();
        $this->update($draft, ['payload' => [...$draft['payload'], 'storage_path' => 'private/path']])->assertUnprocessable()->assertJsonValidationErrors('payload');
        $this->get('/trips/create')->assertInertia(fn (Assert $page) => $page->has('drafts.data', 1)->missing('drafts.data.0.payload')->missing('drafts.data.0.user_id')->where('drafts.data.0.name', 'Alta de prueba'));
        $this->assertSame(1, ImportDraft::first()->revision);
    }
}
