<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevisionContractTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Contrato QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $product = $trip->products()->create(['nombre' => 'Producto', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100', 'subtotal_clp' => 90000, 'costo_final_total_clp' => 90000, 'costo_final_unitario_clp' => '90000']);
        $expense = $trip->expenses()->create(['concepto' => 'Gasto', 'moneda' => 'CLP', 'monto' => '10', 'monto_clp' => 10]);

        return [$trip, $product, $expense];
    }

    public function test_every_existing_import_mutation_requires_a_revision_before_writing(): void
    {
        Storage::fake('local');
        [$trip, $product, $expense] = $this->fixture();
        $doc = Document::create(['trip_id' => $trip->id, 'filename' => 'Conservado.pdf', 'storage_path' => 'documents/retained.pdf', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64), 'kind' => 'other']);
        $base = "/trips/{$trip->id}";
        $cases = [
            ['post', "$base/products", ['nombre' => 'Nuevo', 'cantidad' => 1, 'moneda' => 'USD', 'costo_unitario' => '1']],
            ['put', "$base/products/{$product->id}", ['nombre' => 'Cambio', 'cantidad' => 1, 'moneda' => 'USD', 'costo_unitario' => '1']],
            ['patch', "$base/products/{$product->id}/precio-venta", ['precio_venta' => '200000']],
            ['delete', "$base/products/{$product->id}", []],
            ['post', "$base/expenses", ['concepto' => 'Nuevo', 'moneda' => 'CLP', 'monto' => '1']],
            ['put', "$base/expenses/{$expense->id}", ['concepto' => 'Cambio', 'moneda' => 'CLP', 'monto' => '1']],
            ['delete', "$base/expenses/{$expense->id}", []],
            ['put', $base, ['nombre' => 'Cambio', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '1000']],
            ['patch', "$base/close", []],
            ['delete', $base, []],
            ['post', "$base/documents", ['file' => UploadedFile::fake()->image('blocked.png')]],
            ['delete', "$base/documents/{$doc->id}", []],
        ];
        foreach ($cases as [$method, $url, $payload]) {
            $this->json(strtoupper($method), $url, $payload)->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        }
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame('Producto', $product->fresh()->nombre);
        $this->assertSame('Gasto', $expense->fresh()->concepto);
        $this->assertNull($product->fresh()->precio_venta);
        $this->assertSame(0, $trip->costRuns()->count());
        $this->assertSame(0, $trip->auditEvents()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNotSoftDeleted('documents', ['id' => $doc->id]);
        $trip->update(['estado' => 'finalizado']);
        $this->patchJson("$base/reopen")->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        $this->assertSame('finalizado', $trip->fresh()->estado);
    }

    public function test_company_assignment_requires_complete_revision_map_without_writing(): void
    {
        [$trip] = $this->fixture();
        $company = Company::create(['user_id' => $trip->user_id, 'nombre' => 'Destino', 'activa' => true]);
        $url = "/companies/{$company->id}/assign-trips";
        $this->postJson($url, ['trip_ids' => [$trip->id]])->assertUnprocessable()->assertJsonValidationErrors('expected_revisions');
        $this->postJson($url, ['trip_ids' => [$trip->id], 'expected_revisions' => [999 => 1]])->assertUnprocessable()->assertJsonValidationErrors('expected_revisions');
        $this->postJson($url, ['trip_ids' => [$trip->id], 'expected_revisions' => [$trip->id => 1, 999 => 1]])->assertUnprocessable()->assertJsonValidationErrors('expected_revisions');
        $this->assertNull($trip->fresh()->company_id);
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, $trip->auditEvents()->count());
    }

    public function test_company_switch_is_post_only_and_does_not_accept_foreign_company(): void
    {
        [$trip] = $this->fixture();
        $company = Company::create(['user_id' => $trip->user_id, 'nombre' => 'Propia', 'activa' => true]);
        $foreign = Company::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Ajena', 'activa' => true]);
        $this->get("/company/switch/{$company->id}")->assertStatus(405)->assertSessionMissing('active_company_id');
        $this->post("/company/switch/{$company->id}")->assertRedirect()->assertSessionHas('active_company_id', $company->id);
        $this->post("/company/switch/{$foreign->id}")->assertForbidden()->assertSessionHas('active_company_id', $company->id);
        $this->post('/company/switch/all')->assertRedirect()->assertSessionMissing('active_company_id');
    }

    public function test_company_with_trashed_import_cannot_be_deleted_and_lose_association(): void
    {
        [$trip] = $this->fixture();
        $company = Company::create(['user_id' => $trip->user_id, 'nombre' => 'Conservada', 'activa' => true]);
        $trip->update(['company_id' => $company->id]);
        $this->delete("/trips/{$trip->id}", ['expected_revision' => 1])->assertSessionHasNoErrors();
        $this->delete("/companies/{$company->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertSame($company->id, Trip::withTrashed()->find($trip->id)->company_id);
    }

    public function test_read_for_conflict_is_scoped_and_paid_metadata_is_exact_on_json_save(): void
    {
        [$trip, $product] = $this->fixture();
        $url = "/trips/{$trip->id}/products/{$product->id}";
        $this->getJson($url)->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('editable', true)->assertJsonMissingPath('product.imagen')->assertJsonMissingPath('product.storage_path');
        $data = ['expected_revision' => 1, 'nombre' => 'Pago original', 'moneda' => 'USD', 'cantidad' => 2, 'costo_unitario' => '100', 'rate_mode' => 'paid', 'applied_rate' => '800.12345678', 'rate_source' => 'Comprobante QA', 'rate_date' => '2026-10-03', 'weight' => '2.12345678', 'volume' => '0.00000001'];
        $this->putJson($url, $data)->assertOk()->assertJsonPath('revision', 2);
        $this->assertSame('800.12345678', $product->fresh()->applied_rate);
        $this->assertSame('2.12345678', $product->fresh()->weight);
        $this->assertSame('0.00000001', $product->fresh()->volume);
        $this->assertSame('160025.00', $product->fresh()->subtotal_clp);
        $this->assertSame('160035.00', $product->fresh()->costo_final_total_clp);
        $audit = $trip->auditEvents()->firstOrFail();
        $this->getJson("/trips/{$trip->id}/audit-events/{$audit->id}")->assertOk()->assertJsonFragment(['field' => 'gastos_prorrateados_clp', 'before' => '0.00', 'after' => '10.00']);
        $this->putJson($url, [...$data, 'nombre' => 'Obsoleto'])->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->assertSame('Pago original', $product->fresh()->nombre);
        $this->assertSame(2, $trip->fresh()->revision);
        $other = Trip::create(['user_id' => $trip->user_id, 'nombre' => 'Otro', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->getJson("/trips/{$other->id}/products/{$product->id}")->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }
}
