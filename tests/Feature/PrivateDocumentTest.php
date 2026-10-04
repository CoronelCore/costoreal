<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $u): Trip
    {
        return Trip::create(['user_id' => $u->id, 'nombre' => 'QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
    }

    public function test_private_file_is_authorized_scoped_and_recoverable(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $u = User::factory()->create();
        $t = $this->trip($u);
        $this->actingAs($u)->post("/trips/{$t->id}/documents", ['file' => UploadedFile::fake()->image('receipt.png'), 'expected_revision' => 1])->assertSessionHasNoErrors();
        $d = $t->documents()->firstOrFail();
        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists($d->storage_path);
        $this->get("/trips/{$t->id}/documents/{$d->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->trip(User::factory()->create());
        $this->get("/trips/{$other->id}/documents/{$d->id}")->assertNotFound();
        $this->actingAs($other->user)->get("/trips/{$t->id}/documents/{$d->id}")->assertForbidden();
        $this->actingAs($u)->delete("/trips/{$t->id}/documents/{$d->id}", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('documents', ['id' => $d->id]);
        Storage::disk('local')->assertExists($d->storage_path);
        $this->get("/trips/{$t->id}/documents/{$d->id}")->assertNotFound();
        $this->post("/trips/{$t->id}/documents/{$d->id}/restore", ['expected_revision' => 3])->assertSessionHasNoErrors();
        $this->get("/trips/{$t->id}/documents/{$d->id}")->assertOk();
    }

    public function test_pdf_is_downloaded_as_attachment_and_receipt_edit_is_multipart(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $t = $this->trip($u);
        $file = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $this->actingAs($u)->post("/trips/{$t->id}/expenses", ['expected_revision' => $t->fresh()->revision, 'concepto' => 'Factura', 'moneda' => 'USD', 'monto' => '10', 'comprobante' => $file])->assertSessionHasNoErrors();
        $e = $t->expenses()->firstOrFail();
        $d = Document::findOrFail($e->document_id);
        $response = $this->get("/trips/{$t->id}/documents/{$d->id}")->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->post("/trips/{$t->id}/expenses/{$e->id}", ['expected_revision' => $t->fresh()->revision, '_method' => 'put', 'concepto' => 'Factura corregida', 'moneda' => 'USD', 'monto' => '20', 'comprobante' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        $this->assertSame('Factura corregida', $e->fresh()->concepto);
        $this->assertEquals(18000, $e->fresh()->monto_clp);
        $this->assertSame(2, $t->documents()->count());
        Storage::disk('local')->assertExists($d->storage_path);
    }

    public function test_invalid_expense_edit_preserves_amount_revision_receipt_and_private_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $this->actingAs($user)->post("/trips/{$trip->id}/expenses", [
            'expected_revision' => 1, 'concepto' => 'Flete conservado', 'moneda' => 'USD',
            'monto' => '20.25', 'comprobante' => UploadedFile::fake()->image('original.png'),
        ])->assertSessionHasNoErrors();
        $expense = $trip->expenses()->firstOrFail();
        $before = $expense->getAttributes();
        $revision = $trip->fresh()->revision;
        $files = Storage::disk('local')->allFiles();
        $this->postJson("/trips/{$trip->id}/expenses/{$expense->id}", [
            '_method' => 'put', 'expected_revision' => $revision, 'concepto' => 'Borrador inválido',
            'moneda' => 'USD', 'monto' => '-1', 'eliminar_comprobante' => true,
            'comprobante' => UploadedFile::fake()->createWithContent('invalid.txt', 'No es un comprobante admitido'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['monto', 'comprobante'])
            ->assertJsonPath('errors.monto.0', 'El monto no puede ser negativo.')
            ->assertJsonPath('errors.comprobante.0', 'El comprobante debe ser PDF, JPEG, PNG o WebP.');
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame($revision, $trip->fresh()->revision);
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertSame(1, $trip->documents()->count());
    }

    public function test_edit_without_upload_retains_receipt_and_unlink_keeps_document_available(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $this->actingAs($user)->post("/trips/{$trip->id}/expenses", [
            'expected_revision' => 1, 'concepto' => 'Flete', 'moneda' => 'USD',
            'monto' => '20', 'comprobante' => UploadedFile::fake()->image('receipt.png'),
        ])->assertSessionHasNoErrors();
        $expense = $trip->expenses()->firstOrFail();
        $document = $trip->documents()->firstOrFail();
        $fields = ['_method' => 'put', 'expected_revision' => $trip->fresh()->revision,
            'concepto' => 'Flete corregido sin archivo nuevo', 'moneda' => 'USD', 'monto' => '21.25'];
        $this->post("/trips/{$trip->id}/expenses/{$expense->id}", $fields)->assertSessionHasNoErrors();
        $this->assertSame($document->id, $expense->fresh()->document_id);
        $this->assertSame('Flete corregido sin archivo nuevo', $expense->fresh()->concepto);
        $this->assertEquals(19125, $expense->fresh()->monto_clp);
        $this->post("/trips/{$trip->id}/expenses/{$expense->id}", [
            ...$fields, 'expected_revision' => $trip->fresh()->revision, 'eliminar_comprobante' => true,
        ])->assertSessionHasNoErrors();
        $this->assertNull($expense->fresh()->document_id);
        $this->assertNull($expense->fresh()->comprobante);
        $this->assertSame(1, $trip->documents()->count());
        Storage::disk('local')->assertExists($document->storage_path);
        $this->get("/trips/{$trip->id}/documents/{$document->id}")->assertOk();
    }

    public function test_conflict_discards_staged_file_and_foreign_upload_never_writes(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $t = $this->trip($u);
        $this->actingAs($u)->post("/trips/{$t->id}/documents", ['file' => UploadedFile::fake()->image('conflict.png'), 'expected_revision' => 99])->assertSessionHasErrors('conflict');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, Document::count());
        $other = $this->trip(User::factory()->create());
        $this->post("/trips/{$other->id}/documents", ['file' => UploadedFile::fake()->image('foreign.png'), 'expected_revision' => 1])->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_closed_import_cannot_add_private_documents(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $t = $this->trip($u);
        $t->update(['estado' => 'finalizado']);
        $this->actingAs($u)->post("/trips/{$t->id}/documents", ['file' => UploadedFile::fake()->image('closed.png'), 'expected_revision' => 1])->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_foreign_nested_uploads_leave_files_and_records_untouched(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $own = $this->trip($owner);
        $foreign = $this->trip(User::factory()->create());
        $product = $foreign->products()->create(['nombre' => 'Ajeno', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '1', 'subtotal_clp' => 900, 'costo_final_total_clp' => 900, 'costo_final_unitario_clp' => '900']);
        $expense = $foreign->expenses()->create(['concepto' => 'Ajeno', 'moneda' => 'USD', 'monto' => '1', 'monto_clp' => 900]);
        $this->actingAs($owner)->post("/trips/{$own->id}/products/{$product->id}", ['_method' => 'put', 'nombre' => 'Intrusión', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => 20, 'imagen' => UploadedFile::fake()->image('foreign.png')])->assertNotFound();
        $this->post("/trips/{$own->id}/expenses/{$expense->id}", ['_method' => 'put', 'concepto' => 'Intrusión', 'moneda' => 'USD', 'monto' => 20, 'comprobante' => UploadedFile::fake()->image('foreign.png')])->assertNotFound();
        $this->assertSame('Ajeno', $product->fresh()->nombre);
        $this->assertSame('Ajeno', $expense->fresh()->concepto);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, Document::count());
    }

    public function test_document_lifecycle_preserves_legacy_money_and_audits_real_document_changes_without_new_cost_runs(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $t = $this->trip($u);
        $p = $t->products()->create(['nombre' => 'Importe heredado', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100', 'subtotal_clp' => '99000', 'gastos_prorrateados_clp' => '0', 'costo_final_total_clp' => '99000', 'costo_final_unitario_clp' => '99000']);
        $before = $p->fresh()->getAttributes();
        $this->actingAs($u)->post("/trips/{$t->id}/documents", ['file' => UploadedFile::fake()->image('receipt.png'), 'expected_revision' => 1])->assertSessionHasNoErrors();
        $doc = $t->documents()->firstOrFail();
        $this->delete("/trips/{$t->id}/documents/{$doc->id}", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $this->post("/trips/{$t->id}/documents/{$doc->id}/restore", ['expected_revision' => 3])->assertSessionHasNoErrors();
        $this->assertSame($before, $p->fresh()->getAttributes());
        $this->assertSame(4, $t->fresh()->revision);
        $this->assertSame(0, $t->costRuns()->count());
        $events = $t->auditEvents()->orderBy('id')->get();
        $this->assertSame([1, 0, 1], $events->map(fn ($e) => $e->summary['after']['documents'])->all());
        $this->assertSame([0, 1, 0], $events->map(fn ($e) => $e->summary['before']['documents'])->all());
        foreach ($events as $event) {
            $this->assertSame('99000.00', $event->summary['before']['cost']);
            $this->assertSame('99000.00', $event->summary['after']['cost']);
        }
        Storage::disk('local')->assertExists($doc->storage_path);
    }
}
