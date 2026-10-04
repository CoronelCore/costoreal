<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $user): Trip
    {
        return Trip::create(['user_id' => $user->id, 'nombre' => 'Historial QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
    }

    private function event(Trip $trip, array $before, array $after): AuditEvent
    {
        return AuditEvent::create(['trip_id' => $trip->id, 'actor_id' => $trip->user_id, 'action' => 'product_update', 'revision' => 2, 'before' => $before, 'after' => $after, 'created_at' => now()]);
    }

    public function test_initial_history_has_bounded_summaries_without_loading_full_snapshots(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $snapshot = ['estado' => 'activo', 'tipo_cambio' => '900', 'costo_total' => '100000', 'products' => array_map(fn ($id) => ['id' => $id, 'nombre' => str_repeat('Nombre ', 50), 'cantidad' => 1], range(1, 1000))];
        for ($i = 0; $i < 21; $i++) {
            $this->event($trip, $snapshot, $snapshot);
        }
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->actingAs($user)->get("/trips/{$trip->id}")->assertInertia(fn (Assert $page) => $page->has('history.data', 20)->where('history.total', 21)->where('history.data.0.summary.before.products', 1000)->missing('history.data.0.before')->missing('history.data.0.after'));
        $reads = array_filter($queries, fn ($sql) => str_contains($sql, 'from "audit_events"') && ! str_contains($sql, 'count(*)'));
        $this->assertCount(1, $reads);
        $this->assertStringNotContainsString('"before"', array_values($reads)[0]);
        $this->assertStringNotContainsString('"after"', array_values($reads)[0]);
        $response = $this->getJson("/trips/{$trip->id}/audit-events?page=2")->assertOk()->assertJsonCount(1, 'data');
        $this->assertLessThan(3000, strlen($response->getContent()));
    }

    public function test_paginated_comparison_classifies_changes_and_never_returns_storage_paths(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $products = array_map(fn ($id) => ['id' => $id, 'nombre' => "Producto {$id}", 'cantidad' => 1, 'costo_unitario' => '100', 'imagen' => 'private/secret.png'], range(1, 21));
        $after = array_map(fn ($p) => [...$p, 'cantidad' => 2], $products);
        $event = $this->event($trip, ['id' => $trip->id, 'tipo_cambio' => '900', 'products' => $products, 'documents' => [['id' => 1, 'filename' => 'Factura.pdf', 'storage_path' => 'private/secret.pdf']]], ['id' => $trip->id, 'tipo_cambio' => '1000', 'products' => $after]);
        $response = $this->actingAs($user)->getJson("/trips/{$trip->id}/audit-events/{$event->id}")->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 23)->assertJsonPath('last_page', 2)->assertJsonPath('data.0.kind', 'trip')->assertJsonPath('data.0.fields.0.field', 'tipo_cambio')->assertJsonPath('data.1.change', 'updated')->assertJsonPath('data.1.fields.0.before', 1)->assertJsonPath('data.1.fields.0.after', 2);
        $this->assertStringNotContainsString('private/secret', $response->getContent());
        $this->getJson("/trips/{$trip->id}/audit-events/{$event->id}?page=2")->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.2.kind', 'documents')->assertJsonPath('data.2.change', 'removed');
        $this->getJson("/trips/{$trip->id}/audit-events/{$event->id}?page=0")->assertUnprocessable();
    }

    public function test_audit_binding_and_read_authorization_reject_foreign_events(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);
        $event = $this->event($trip, [], ['products' => [['id' => 1, 'nombre' => 'Agregado']]]);
        $other = $this->trip($user);
        $this->actingAs($user)->getJson("/trips/{$other->id}/audit-events/{$event->id}")->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson("/trips/{$trip->id}/audit-events")->assertForbidden();
        $this->getJson("/trips/{$trip->id}/audit-events/{$event->id}")->assertForbidden();
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_reentrant_backfill_preserves_raw_snapshots_and_financial_revision(): void
    {
        $trip = $this->trip(User::factory()->create());
        $event = $this->event($trip, ['estado' => 'activo', 'costo_total' => '100'], ['estado' => 'finalizado', 'costo_total' => '200']);
        DB::table('audit_events')->where('id', $event->id)->update(['summary' => null]);
        $raw = DB::table('audit_events')->where('id', $event->id)->first(['before', 'after']);
        $this->artisan('costoreal:audit-backfill', ['--chunk' => 1])->expectsOutput('Resúmenes completados: 1. Los snapshots y costos originales se conservaron.')->assertSuccessful();
        $this->assertSame('100', $event->fresh()->summary['before']['cost']);
        $this->assertSame('200', $event->fresh()->summary['after']['cost']);
        $this->artisan('costoreal:audit-backfill')->expectsOutput('Resúmenes completados: 0. Los snapshots y costos originales se conservaron.')->assertSuccessful();
        $this->assertEquals($raw, DB::table('audit_events')->where('id', $event->id)->first(['before', 'after']));
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, $trip->costRuns()->count());
        $this->artisan('costoreal:audit-backfill', ['--chunk' => 0])->assertFailed();
    }
}
