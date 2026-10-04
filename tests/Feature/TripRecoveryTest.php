<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TripRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $user, array $data = []): Trip
    {
        return Trip::create([...['user_id' => $user->id, 'nombre' => 'Recuperación QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo'], ...$data]);
    }

    public function test_closed_import_restores_exact_costs_files_scenarios_and_original_closed_state(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $this->actingAs($user)->post("/trips/{$trip->id}/products", ['nombre' => 'Producto', 'moneda' => 'USD', 'cantidad' => 1, 'costo_unitario' => '100', 'expected_revision' => 1])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/documents", ['file' => UploadedFile::fake()->image('receipt.png'), 'expected_revision' => 2])->assertSessionHasNoErrors();
        $this->postJson("/trips/{$trip->id}/scenarios", ['name' => 'Simulación conservada', 'idempotency_key' => (string) Str::uuid(), 'expected_revision' => 3, 'reference_rate' => '1000'])->assertOk();
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => 3])->assertSessionHasNoErrors();
        $document = $trip->documents()->firstOrFail();
        $products = $trip->products()->get()->toArray();
        $runs = $trip->costRuns()->get()->toArray();
        $scenario = $trip->scenarios()->firstOrFail()->toArray();
        $this->delete("/trips/{$trip->id}", ['expected_revision' => 4])->assertRedirect('/trips')->assertSessionHasNoErrors();
        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
        $this->assertSame(5, Trip::withTrashed()->find($trip->id)->revision);
        $this->get("/trips/{$trip->id}")->assertNotFound();
        $this->get("/trips/{$trip->id}/documents/{$document->id}")->assertNotFound();
        $this->get('/trips')->assertInertia(fn (Assert $page) => $page->where('metrics.total', '0')->where('trips.total', 0));
        $this->get('/trips/trash')->assertInertia(fn (Assert $page) => $page->component('Trips/Trash')->where('trips.total', 1)->where('trips.data.0.costo_total', '90000')->where('trips.data.0.estado', 'finalizado')->missing('trips.data.0.products')->missing('trips.data.0.documents'));
        Storage::disk('local')->assertExists($document->storage_path);
        $this->post("/trips/{$trip->id}/restore", ['expected_revision' => 5])->assertRedirect("/trips/{$trip->id}")->assertSessionHasNoErrors();
        $this->assertSame(6, $trip->fresh()->revision);
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertSame('900.00000000', $trip->fresh()->tipo_cambio);
        $this->assertSame($products, $trip->products()->get()->toArray());
        $this->assertSame($runs, $trip->costRuns()->get()->toArray());
        $this->assertSame($scenario, $trip->scenarios()->firstOrFail()->toArray());
        $this->assertSame(['trip_restore', 'trip_trash'], $trip->auditEvents()->latest('id')->limit(2)->pluck('action')->all());
        $this->get("/trips/{$trip->id}/documents/{$document->id}")->assertOk();
        $this->post("/trips/{$trip->id}/products", ['nombre' => 'No editar', 'cantidad' => 1, 'moneda' => 'USD', 'costo_unitario' => '2', 'expected_revision' => 6])->assertForbidden();
    }

    public function test_recovery_requires_current_revision_and_rejects_foreign_imports(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $this->actingAs($owner)->delete("/trips/{$trip->id}")->assertSessionHasErrors('expected_revision');
        $this->delete("/trips/{$trip->id}", ['expected_revision' => 99])->assertSessionHasErrors('conflict');
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, $trip->auditEvents()->count());
        $other = User::factory()->create();
        $this->actingAs($other)->delete("/trips/{$trip->id}", ['expected_revision' => 1])->assertForbidden();
        $this->actingAs($owner)->delete("/trips/{$trip->id}", ['expected_revision' => 1])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/restore", ['expected_revision' => 1])->assertSessionHasErrors('conflict');
        $this->post("/trips/{$trip->id}/restore")->assertSessionHasErrors('expected_revision');
        $this->actingAs($other)->post("/trips/{$trip->id}/restore", ['expected_revision' => 2])->assertForbidden();
        $this->get('/trips/trash')->assertInertia(fn (Assert $page) => $page->where('trips.total', 0));
        $this->assertSame(2, Trip::withTrashed()->find($trip->id)->revision);
        $this->actingAs($owner)->post("/trips/{$trip->id}/restore", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/restore", ['expected_revision' => 3])->assertForbidden();
        $this->assertSame(3, $trip->fresh()->revision);
    }

    public function test_trash_list_is_company_scoped_searchable_and_paginated(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id, 'nombre' => 'Empresa QA', 'activa' => true]);
        for ($i = 1; $i <= 21; $i++) {
            $trip = $this->trip($user, ['company_id' => $company->id, 'nombre' => "Archivada {$i}"]);
            $trip->delete();
        }
        $this->trip($user, ['nombre' => 'Sin empresa'])->delete();
        $this->trip(User::factory()->create())->delete();
        $this->actingAs($user)->withSession(['active_company_id' => $company->id])->get('/trips/trash')
            ->assertInertia(fn (Assert $page) => $page->where('trips.total', 21)->has('trips.data', 20));
        $this->get('/trips/trash?page=2')->assertInertia(fn (Assert $page) => $page->has('trips.data', 1));
        $this->get('/trips/trash?q=Archivada%2021')->assertInertia(fn (Assert $page) => $page->where('trips.total', 1));
    }

    public function test_audit_failure_rolls_back_soft_delete_and_restore_with_revision(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $trashed = $this->trip($user);
        $trashed->delete();
        $this->actingAs($user);
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "audit_events"')) {
                throw new \RuntimeException('Synthetic recovery failure');
            }
        });
        $this->withoutExceptionHandling();
        foreach ([['delete', "/trips/{$trip->id}"], ['post', "/trips/{$trashed->id}/restore"]] as [$method, $path]) {
            try {
                $this->{$method}($path, ['expected_revision' => 1]);
                $this->fail('Failure expected');
            } catch (\RuntimeException $error) {
                $this->assertSame('Synthetic recovery failure', $error->getMessage());
            }
        }
        $this->assertNotSoftDeleted('trips', ['id' => $trip->id]);
        $this->assertSoftDeleted('trips', ['id' => $trashed->id]);
        $this->assertSame(1, Trip::withTrashed()->find($trip->id)->revision);
        $this->assertSame(1, Trip::withTrashed()->find($trashed->id)->revision);
        $this->assertSame(0, DB::table('audit_events')->count());
    }
}
