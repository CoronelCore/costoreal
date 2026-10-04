<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Observability\RequestMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PerformanceObservationTest extends TestCase
{
    use RefreshDatabase;

    private function observe(array &$records): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')->andReturnUsing(function ($level, $message, $context) use (&$records): void {
            $records[] = ['level' => $level, 'message' => $message, 'metrics' => $context];
        });
        Log::shouldReceive('channel')->with('performance')->andReturn($logger);
    }

    public function test_budget_warning_contains_action_and_metrics_without_financial_or_identity_data(): void
    {
        $user = User::factory()->create(['name' => 'Nombre privado de prueba', 'email' => 'secret-owner@example.test']);
        Trip::create(['user_id' => $user->id, 'nombre' => 'Operación confidencial 12345', 'moneda_base' => 'CLP',
            'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $records = [];
        $this->observe($records);
        // Route names contain dots; replace the map entry explicitly rather than config dot traversal.
        $routes = config('performance.routes');
        $routes['trips.index'] = ['elapsed_ms' => 0, 'query_count' => 0, 'response_bytes' => 0, 'action' => 'Repetir perfil y revisar consultas.'];
        config(['performance.routes' => $routes]);
        $this->actingAs($user)->get('/trips?token=private-token-value')->assertOk();
        $this->assertCount(1, $records);
        $this->assertSame('warning', $records[0]['level']);
        $this->assertSame('performance.budget_exceeded', $records[0]['message']);
        $metrics = $records[0]['metrics'];
        $this->assertSame('trips.index', $metrics['route']);
        $this->assertSame(200, $metrics['status']);
        $this->assertSame('Repetir perfil y revisar consultas.', $metrics['budget']['action']);
        $this->assertGreaterThan(0, $metrics['query_count']);
        $this->assertGreaterThan(0, $metrics['response_bytes']);
        $this->assertContains('query_count', $metrics['exceeded']);
        $serialized = json_encode($records, JSON_THROW_ON_ERROR);
        foreach (['Nombre privado', 'secret-owner', 'confidencial', '12345', 'private-token-value', 'select ', 'binding', '/trips', 'password'] as $private) {
            $this->assertStringNotContainsString($private, $serialized);
        }
    }

    public function test_counters_reset_between_requests_and_do_not_capture_queries_outside_request(): void
    {
        $user = User::factory()->create();
        $records = [];
        $this->observe($records);
        config(['performance.log_all' => true]);
        $this->actingAs($user)->get('/trips')->assertOk();
        for ($i = 0; $i < 8; $i++) {
            DB::select('select 1');
        }
        $this->get('/trips')->assertOk();
        $this->assertCount(2, $records);
        $this->assertSame($records[0]['metrics']['query_count'], $records[1]['metrics']['query_count']);
        $this->assertLessThan(8, $records[1]['metrics']['query_count']);
    }

    public function test_disabled_or_under_budget_requests_do_not_write_samples_or_add_public_diagnostics(): void
    {
        $user = User::factory()->create();
        Log::shouldReceive('channel')->never();
        $routes = config('performance.routes');
        $routes['trips.index'] = [...$routes['trips.index'], 'elapsed_ms' => 1000000];
        config(['performance.routes' => $routes]);
        $this->actingAs($user)->get('/trips')->assertOk()->assertHeaderMissing('Server-Timing')
            ->assertHeaderMissing('X-Query-Count')->assertDontSee('costoreal.performance');
        config(['performance.enabled' => false, 'performance.log_all' => true]);
        $this->get('/analytics')->assertOk();
    }

    public function test_failed_diagnostic_write_keeps_success_response_and_allows_the_next_request(): void
    {
        $user = User::factory()->create();
        config(['performance.log_all' => true]);
        Log::shouldReceive('channel')->with('performance')->twice()->andThrow(new \RuntimeException('Log destination unavailable'));
        $this->actingAs($user)->get('/trips')->assertOk();
        $this->get('/trips')->assertOk();
        $metrics = app(RequestMetrics::class);
        $metrics->begin();
        $this->assertSame(0, $metrics->finish()['query_count']);
    }

    public function test_forbidden_route_preserves_403_and_records_no_parent_identity(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::create(['user_id' => $owner->id, 'nombre' => 'Nombre que no debe registrarse',
            'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $records = [];
        $this->observe($records);
        config(['performance.log_all' => true]);
        $this->actingAs(User::factory()->create())->get('/trips/'.$trip->id)->assertForbidden();
        $this->assertSame(403, $records[0]['metrics']['status']);
        $this->assertSame('trips.show', $records[0]['metrics']['route']);
        $this->assertArrayNotHasKey('trip_id', $records[0]['metrics']);
        $this->assertStringNotContainsString($trip->nombre, json_encode($records));
    }

    public function test_private_stream_size_is_measured_without_logging_path_or_filename(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Comprobante sintético',
            'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $content = str_repeat('x', 2170);
        Storage::disk('local')->put('private-secret-receipt.png', $content);
        $document = Document::create(['trip_id' => $trip->id, 'storage_path' => 'private-secret-receipt.png',
            'filename' => 'private-supplier-name.png', 'mime' => 'image/png', 'size' => strlen($content), 'sha256' => hash('sha256', $content), 'kind' => 'receipt']);
        $records = [];
        $this->observe($records);
        config(['performance.log_all' => true]);
        $this->actingAs($user)->get("/trips/{$trip->id}/documents/{$document->id}")->assertOk();
        $this->assertSame(2170, $records[0]['metrics']['response_bytes']);
        $this->assertStringNotContainsString('private-secret-receipt', json_encode($records));
        $this->assertStringNotContainsString('private-supplier-name', json_encode($records));
    }
}
