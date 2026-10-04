<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Documents\MigrateLegacyDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyDocumentMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function source(string $path = 'productos/original.png'): array
    {
        Storage::fake('public');
        Storage::fake('local');
        $trip = Trip::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Adjuntos heredados', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '950.12345678', 'estado' => 'finalizado']);
        $product = $trip->products()->create(['nombre' => 'Compra original', 'moneda' => 'USD', 'cantidad' => 2, 'costo_unitario' => '10.25', 'subtotal_clp' => '19477.53', 'gastos_prorrateados_clp' => '125.50', 'costo_final_total_clp' => '19603.03', 'costo_final_unitario_clp' => '9801.515', 'precio_venta' => '12000', 'imagen' => $path]);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a31cAAAAASUVORK5CYII=');
        Storage::disk('public')->put($path, $bytes);

        return [$trip->fresh(), $product->fresh(), $bytes];
    }

    public function test_inventory_is_read_only_and_apply_preserves_finance_and_verified_bytes(): void
    {
        [$trip,$product,$bytes] = $this->source();
        $old = $product->getAttributes();
        $tripOld = $trip->getAttributes();
        $this->artisan('costoreal:legacy-documents')->assertSuccessful();
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('legacy_document_moves', 0);
        Storage::disk('public')->assertExists('productos/original.png');
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        $doc = Document::firstOrFail();
        $this->assertSame($bytes, Storage::disk('local')->get($doc->storage_path));
        Storage::disk('public')->assertMissing('productos/original.png');
        $this->assertSame(hash('sha256', $bytes), $doc->sha256);
        $this->assertSame('image/png', $doc->mime);
        $this->assertNull($doc->uploaded_by);
        $this->assertSame($doc->id, $product->fresh()->document_id);
        $this->assertSame($old, array_replace($product->fresh()->getAttributes(), ['imagen' => $old['imagen'], 'document_id' => $old['document_id']]));
        $this->assertSame($tripOld, $trip->fresh()->getAttributes());
        $this->assertDatabaseCount('cost_runs', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseHas('legacy_document_moves', ['status' => 'retired', 'sha256' => $doc->sha256]);
        $this->actingAs(User::findOrFail($trip->user_id))->get("/trips/{$trip->id}/documents/{$doc->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('legacy_document_moves', 1);
    }

    public function test_shared_public_file_is_retired_only_when_all_parent_references_are_migrated(): void
    {
        [$trip,$product,$bytes] = $this->source();
        $other = $trip->products()->create([...$product->getAttributes(), 'id' => null, 'nombre' => 'Otra referencia']);
        $migration = app(MigrateLegacyDocument::class);
        $first = $migration->migrate('products', DB::table('products')->find($product->id));
        $this->assertSame('awaiting_references', $migration->retire($first));
        Storage::disk('public')->assertExists('productos/original.png');
        $second = $migration->migrate('products', DB::table('products')->find($other->id));
        $this->assertSame('retired', $migration->retire($second));
        $this->assertSame('retired', $migration->retire($first));
        $this->assertDatabaseCount('documents', 2);
        foreach (Document::all() as $document) {
            $this->assertSame($bytes, Storage::disk('local')->get($document->storage_path));
        }
    }

    public function test_metadata_failure_rolls_back_private_stage_and_keeps_public_source(): void
    {
        [$trip,$product] = $this->source();
        Document::creating(fn () => throw new \RuntimeException('Metadata unavailable'));
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertFailed();
        Storage::disk('public')->assertExists('productos/original.png');
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
        $this->assertSame('productos/original.png', $product->fresh()->imagen);
        $this->assertNull($product->fresh()->document_id);
        $this->assertDatabaseCount('legacy_document_moves', 0);
    }

    public function test_cleanup_failure_is_resumable_and_never_discards_the_committed_private_copy(): void
    {
        [$trip,$product,$bytes] = $this->source();
        $migration = app(MigrateLegacyDocument::class);
        $move = $migration->migrate('products', DB::table('products')->find($product->id));
        Storage::disk('public')->put('productos/original.png', 'changed externally');
        try {
            $migration->retire($move);
            $this->fail('Changed public bytes must not be removed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cambió', $e->getMessage());
        }
        $this->assertDatabaseHas('legacy_document_moves', ['id' => $move, 'status' => 'cleanup_failed']);
        $this->assertSame($bytes, Storage::disk('local')->get($product->fresh()->imagen));
        Storage::disk('public')->assertExists('productos/original.png');
        Storage::disk('public')->put('productos/original.png', $bytes);
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('legacy_document_moves', ['id' => $move, 'status' => 'retired']);
    }

    public function test_unsafe_missing_and_symlinked_sources_are_never_copied_or_changed(): void
    {
        [$trip,$product] = $this->source();
        foreach (['../outside.png', 'https://example.com/private.png', 'productos/missing.png'] as $path) {
            DB::table('products')->where('id', $product->id)->update(['imagen' => $path]);
            $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertFailed();
            $this->assertSame($path, $product->fresh()->imagen);
            $this->assertDatabaseCount('documents', 0);
        }
        symlink(Storage::disk('public')->path('productos/original.png'), Storage::disk('public')->path('productos/link.png'));
        DB::table('products')->where('id', $product->id)->update(['imagen' => 'productos/link.png']);
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_expense_trashed_import_and_existing_attachment_are_preserved(): void
    {
        [$trip,$product,$bytes] = $this->source();
        $doc = Document::create(['trip_id' => $trip->id, 'storage_path' => 'documents/existing', 'filename' => 'existente.pdf', 'mime' => 'application/pdf', 'size' => 4, 'sha256' => hash('sha256', 'kept'), 'kind' => 'other']);
        Storage::disk('local')->put($doc->storage_path, 'kept');
        DB::table('products')->where('id', $product->id)->update(['document_id' => $doc->id]);
        $expense = $trip->expenses()->create(['concepto' => 'Comprobante anterior', 'moneda' => 'CLP', 'monto' => '125.50', 'monto_clp' => '125.50', 'comprobante' => 'comprobantes/legacy.pdf']);
        Storage::disk('public')->put('comprobantes/legacy.pdf', "%PDF-1.4\nlegacy");
        $trip->delete();
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        $this->assertSame($doc->id, $product->fresh()->document_id);
        $this->assertSame('kept', Storage::disk('local')->get($doc->storage_path));
        $this->assertDatabaseCount('documents', 3);
        $this->assertNotNull($expense->fresh()->document_id);
        $this->assertSame('125.50', $expense->fresh()->monto_clp);
        $this->assertNotNull(Trip::withTrashed()->findOrFail($trip->id)->deleted_at);
    }

    public function test_a_corrupt_private_copy_or_an_existing_worker_blocks_retirement(): void
    {
        [$trip,$product] = $this->source();
        $migration = app(MigrateLegacyDocument::class);
        $move = $migration->migrate('products', DB::table('products')->find($product->id));
        Storage::disk('local')->put($product->fresh()->imagen, 'corrupt');
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertFailed();
        Storage::disk('public')->assertExists('productos/original.png');
        $file = fopen(Storage::disk('local')->path('.legacy-document-migration.lock'), 'c+');
        flock($file, LOCK_EX | LOCK_NB);
        try {
            $this->artisan('costoreal:legacy-documents', ['--apply' => true])->expectsOutputToContain('Otra migración')->assertFailed();
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function test_unreferenced_files_require_an_explicit_private_quarantine_and_keep_a_recoverable_manifest(): void
    {
        [$trip, $product, $bytes] = $this->source();
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->put('productos/unassigned.png', $bytes);
        $this->artisan('costoreal:legacy-documents')->expectsOutputToContain('archivos sin referencia: 1')->assertSuccessful();
        $this->assertDatabaseCount('legacy_document_quarantine', 0);
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertFailed();
        Storage::disk('public')->assertExists('productos/unassigned.png');
        $this->artisan('costoreal:legacy-documents', ['--apply' => true, '--quarantine-orphans' => true])->assertSuccessful();
        $record = DB::table('legacy_document_quarantine')->first();
        $this->assertSame('retired', $record->status);
        $this->assertSame($bytes, Storage::disk('local')->get($record->storage_path));
        $this->assertSame(hash('sha256', $bytes), $record->sha256);
        Storage::disk('public')->assertMissing('productos/unassigned.png');
        $this->assertDatabaseCount('documents', 1);
        $this->artisan('costoreal:legacy-documents', ['--apply' => true, '--quarantine-orphans' => true])->assertSuccessful();
        $this->assertDatabaseCount('legacy_document_quarantine', 1);
    }

    public function test_quarantine_batches_are_bounded_and_do_not_change_existing_associations(): void
    {
        [$trip, $product, $bytes] = $this->source();
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        $before = $product->fresh()->getAttributes();
        for ($i = 0; $i < 3; $i++) {
            Storage::disk('public')->put("productos/loose-$i.png", $bytes);
        }
        $options = ['--apply' => true, '--quarantine-orphans' => true, '--limit' => 1];
        $this->artisan('costoreal:legacy-documents', $options)->assertFailed();
        $this->assertDatabaseCount('legacy_document_quarantine', 1);
        $this->artisan('costoreal:legacy-documents', $options)->assertFailed();
        $this->artisan('costoreal:legacy-documents', $options)->assertSuccessful();
        $this->assertDatabaseCount('legacy_document_quarantine', 3);
        $this->assertSame($before, $product->fresh()->getAttributes());
    }

    public function test_duplicate_content_notices_are_scoped_to_active_documents_of_the_owned_import(): void
    {
        [$trip, $product] = $this->source();
        $this->artisan('costoreal:legacy-documents', ['--apply' => true])->assertSuccessful();
        $original = Document::firstOrFail();
        $other = Trip::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Otro propietario', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => 900, 'estado' => 'activo']);
        Document::create([...$original->getAttributes(), 'id' => null, 'trip_id' => $other->id, 'storage_path' => 'documents/foreign']);
        $this->actingAs(User::findOrFail($trip->user_id));
        $first = $this->get("/trips/{$trip->id}")->assertOk()->viewData('page')['props']['trip']['documents'];
        $this->assertSame(1, $first[0]['same_content_count']);
        $duplicate = Document::create([...$original->getAttributes(), 'id' => null, 'storage_path' => 'documents/duplicate']);
        $read = $this->get("/trips/{$trip->id}")->assertOk()->viewData('page')['props']['trip']['documents'];
        $this->assertSame([2, 2], array_column($read, 'same_content_count'));
        $duplicate->delete();
        $read = $this->get("/trips/{$trip->id}")->assertOk()->viewData('page')['props']['trip']['documents'];
        $this->assertSame([1], array_column($read, 'same_content_count'));
    }
}
