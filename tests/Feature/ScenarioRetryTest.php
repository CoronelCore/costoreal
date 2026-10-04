<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScenarioRetryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_document_restoration_retry_reloads_model_after_rollback(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Documento QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->actingAs($user)->post("/trips/{$trip->id}/documents", ['file' => UploadedFile::fake()->image('receipt.png'), 'expected_revision' => 1])->assertSessionHasNoErrors();
        $document = $trip->documents()->firstOrFail();
        $this->delete("/trips/{$trip->id}/documents/{$document->id}", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $attempts = 0;
        DB::listen(function ($query) use (&$attempts) {
            if (str_contains($query->sql, 'insert into "cost_runs"') && ++$attempts === 1) {
                throw new \RuntimeException('database is locked');
            }
        });
        $this->post("/trips/{$trip->id}/documents/{$document->id}/restore", ['expected_revision' => 3])->assertSessionHasNoErrors();
        $this->assertSame(2, $attempts);
        $this->assertSame(4, $trip->fresh()->revision);
        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);
        $this->assertSame(1, $trip->costRuns()->where('reason', 'document_restore')->count());
        $this->assertSame(1, $trip->auditEvents()->where('action', 'document_restore')->count());
        $this->assertSame(1, $trip->auditEvents()->latest('id')->first()->summary['after']['documents']);
        Storage::disk('local')->assertExists($document->storage_path);
    }

    public function test_retry_after_recording_failure_reloads_scenario_and_records_promotion_once(): void
    {
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Reintento QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->actingAs($user)->post("/trips/{$trip->id}/products", ['nombre' => 'Producto', 'cantidad' => 1, 'moneda' => 'USD', 'costo_unitario' => '100', 'expected_revision' => 1])->assertSessionHasNoErrors();
        $this->postJson("/trips/{$trip->id}/scenarios", ['name' => 'Nueva tasa', 'idempotency_key' => (string) Str::uuid(), 'expected_revision' => 2, 'reference_rate' => '1000'])->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $attempts = 0;
        DB::listen(function ($query) use (&$attempts) {
            if (str_contains($query->sql, 'insert into "cost_runs"') && ++$attempts === 1) {
                throw new \RuntimeException('database is locked');
            }
        });
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $attempts);
        $this->assertSame(3, $trip->fresh()->revision);
        $this->assertSame(3, $scenario->fresh()->applied_revision);
        $this->assertNotNull($scenario->fresh()->applied_at);
        $this->assertSame('100000.00', $trip->products()->first()->costo_final_total_clp);
        $this->assertSame(1, $trip->costRuns()->where('reason', 'scenario_apply')->count());
        $this->assertSame(1, $trip->auditEvents()->where('action', 'scenario_apply')->count());
    }
}
