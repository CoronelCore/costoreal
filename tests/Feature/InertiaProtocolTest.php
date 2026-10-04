<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Tests\TestCase;

class InertiaProtocolTest extends TestCase
{
    use RefreshDatabase;

    private function headers(array $extra = []): array
    {
        return ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion() ?? '', ...$extra];
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/trips')->assertOk();

        return $user;
    }

    public function test_initial_page_uses_safe_json_script_and_the_correct_vue_directory(): void
    {
        $name = '</script><script>alert("unsafe")</script>';
        $user = User::factory()->create(['name' => $name]);
        $response = $this->actingAs($user)->get('/profile')->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $match));
        $page = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($name, $page['props']['auth']['user']['name']);
        $this->assertSame('Profile/Edit', $page['component']);
        $this->assertStringNotContainsString($name, $html);
        $this->assertArrayNotHasKey('password', $page['props']['auth']['user']);
        $this->assertStringNotContainsString('<div id="app" data-page=', $html);
        $this->assertSame([resource_path('js/Pages')], config('inertia.pages.paths'));
        $this->assertTrue(config('inertia.pages.ensure_pages_exist'));
    }

    public function test_partial_reload_preserves_validation_and_excludes_unrequested_financial_data(): void
    {
        $this->authenticate();
        $response = $this->get('/trips', $this->headers([
            'X-Inertia-Partial-Component' => 'Trips/Index',
            'X-Inertia-Partial-Data' => 'metrics',
        ]))->assertOk()->assertHeader('X-Inertia', 'true');
        $this->assertArrayHasKey('metrics', $response->json('props'));
        $this->assertArrayHasKey('errors', $response->json('props'));
        $this->assertArrayNotHasKey('trips', $response->json('props'));
        $this->assertArrayNotHasKey('auth', $response->json('props'));
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
    }

    public function test_named_validation_bag_redirects_to_get_and_keeps_the_profile_unchanged(): void
    {
        $user = $this->authenticate();
        $headers = $this->headers(['X-Inertia-Error-Bag' => 'profile']);
        $this->from('/profile')->patch('/profile', ['name' => ' ', 'email' => $user->email], $headers)
            ->assertStatus(303)->assertRedirect('/profile')->assertSessionHasErrors('name');
        $response = $this->get('/profile', $headers)->assertOk();
        $this->assertIsString($response->json('props.errors.profile.name'));
        $this->assertNull($response->json('props.errors.updatePassword'));
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_success_message_is_native_flash_even_in_partial_responses_and_consumed_once(): void
    {
        $this->authenticate();
        $this->withSession(['success' => 'Documento guardado.']);
        $headers = $this->headers(['X-Inertia-Partial-Component' => 'Trips/Index', 'X-Inertia-Partial-Data' => 'metrics']);
        $first = $this->get('/trips', $headers)->assertOk();
        $this->assertSame('Documento guardado.', $first->json('flash.success'));
        $this->assertArrayNotHasKey('flash', $first->json('props'));
        $this->get('/trips', $headers)->assertOk()->assertJsonMissingPath('flash.success');
    }

    public function test_multipart_document_redirect_keeps_flash_and_does_not_recalculate_costs(): void
    {
        Storage::fake('local');
        $user = $this->authenticate();
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Multipart v3', 'moneda_trabajo' => 'CLP',
            'moneda_base' => 'CLP', 'tipo_cambio' => '1', 'estado' => 'activo']);
        $product = $trip->products()->create(['nombre' => 'Importe heredado', 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => '99000', 'subtotal_clp' => '99000', 'gastos_prorrateados_clp' => '0', 'costo_final_total_clp' => '99000', 'costo_final_unitario_clp' => '99000']);
        $before = $product->fresh()->getAttributes();
        $this->from("/trips/{$trip->id}")->post("/trips/{$trip->id}/documents", [
            'expected_revision' => $trip->fresh()->revision, 'file' => UploadedFile::fake()->image('inertia.png'),
        ], $this->headers())->assertRedirect("/trips/{$trip->id}")->assertSessionHasNoErrors();
        $page = $this->get("/trips/{$trip->id}", $this->headers())->assertOk();
        $this->assertNotEmpty($page->json('flash.success'));
        $this->assertCount(1, $page->json('props.trip.documents'));
        $this->assertSame('99000.00', $page->json('props.trip.costo_total'));
        $this->assertDatabaseCount('cost_runs', 0);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(2, $trip->fresh()->revision);
        $document = $trip->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->storage_path);
    }

    public function test_old_asset_version_requires_a_reload_and_no_diagnostics_are_exposed(): void
    {
        $this->authenticate();
        $this->get('/trips', $this->headers(['X-Inertia-Version' => 'previous-build']))
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/trips'));
        $this->assertFalse(config('inertia.devtools.enabled'));
        $this->assertFalse(config('inertia.ssr.enabled'));
        $this->get('/_inertia/devtools')->assertNotFound();
    }

    public function test_asset_reload_preserves_the_pending_confirmation_exactly_once(): void
    {
        $this->authenticate();
        $this->withSession(['success' => 'Importación guardada.']);
        $this->get('/trips', $this->headers(['X-Inertia-Version' => 'previous-build']))->assertStatus(409);
        $this->get('/trips', $this->headers())->assertOk()->assertJsonPath('flash.success', 'Importación guardada.');
        $this->get('/trips', $this->headers())->assertOk()->assertJsonMissingPath('flash.success');
    }
}
