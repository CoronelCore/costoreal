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
}
