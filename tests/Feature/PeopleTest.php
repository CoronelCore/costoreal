<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackAccountAccess;
use App\Models\Trip;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $user): Trip
    {
        return Trip::create(['user_id' => $user->id, 'nombre' => 'Importación ajena', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
    }

    public function test_only_the_owner_sees_people_and_never_their_data(): void
    {
        $owner = User::factory()->create(['name' => 'Manuel']);
        $friend = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);
        $this->trip($friend);
        $this->trip($friend);

        $this->actingAs($friend)->get('/personas')->assertForbidden();
        $this->get('/trips')->assertInertia(fn (Assert $page) => $page->where('auth.user.is_owner', false)->etc());
        foreach (['suspender' => 'patch', 'reactivar' => 'patch', 'enlace-contrasena' => 'post'] as $action => $method) {
            $this->{$method}("/personas/{$owner->id}/{$action}")->assertForbidden();
        }

        $this->actingAs($owner)->get('/personas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('People/Index')
            ->where('auth.user.is_owner', true)
            ->has('people', 2)
            ->where('people.0.is_owner', true)
            ->where('people.1.name', 'Ana')
            ->where('people.1.email', 'ana@example.test')
            ->where('people.1.trips_count', 2)
            ->where('people.1.suspended', false)
            ->missing('people.1.trips')
            ->etc());
    }

    public function test_visits_record_the_last_activity_at_most_every_few_minutes(): void
    {
        User::factory()->create();
        $friend = User::factory()->create();
        $this->assertNull($friend->last_seen_at);

        $this->travelTo(now()->startOfMinute());
        $this->actingAs($friend)->get('/trips')->assertOk();
        $first = $friend->fresh()->last_seen_at;
        $this->assertTrue($first->equalTo(now()));

        $this->travel(2)->minutes();
        $this->get('/trips')->assertOk();
        $this->assertTrue($friend->fresh()->last_seen_at->equalTo($first));

        $this->travel(4)->minutes();
        $this->get('/trips')->assertOk();
        $this->assertTrue($friend->fresh()->last_seen_at->equalTo(now()));
    }

    public function test_a_suspended_account_is_signed_out_and_cannot_sign_in_until_reactivated(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create(['email' => 'ana@example.test', 'password' => Hash::make('clave-de-ana-1')]);
        $this->trip($friend);

        $this->actingAs($owner)->patch("/personas/{$owner->id}/suspender")->assertSessionHasErrors('person');
        $this->assertNull($owner->fresh()->suspended_at);
        $this->patch("/personas/{$friend->id}/suspender")->assertRedirect(route('people.index'));
        $this->assertNotNull($friend->fresh()->suspended_at);
        $this->assertDatabaseCount('trips', 1);

        // An open session ends on the next visit.
        $this->actingAs($friend->fresh())->get('/trips')->assertRedirect(route('login'))
            ->assertSessionHas('status', TrackAccountAccess::SUSPENDED);
        $this->assertGuest();

        $this->post('/login', ['email' => 'ana@example.test', 'password' => 'clave-de-ana-1'])
            ->assertSessionHasErrors(['email' => TrackAccountAccess::SUSPENDED]);
        $this->assertGuest();

        $this->actingAs($owner)->patch("/personas/{$friend->id}/reactivar")->assertRedirect(route('people.index'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'ana@example.test', 'password' => 'clave-de-ana-1'])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($friend);
    }

    public function test_the_owner_hands_out_a_password_link_that_works_once(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create(['email' => 'ana@example.test']);

        $this->actingAs($owner)->post("/personas/{$friend->id}/enlace-contrasena")->assertRedirect(route('people.index'));
        $link = session('inertia.flash_data')['link'] ?? null;
        $this->assertSame('password', $link['kind']);
        $this->assertSame(24, $link['hours']);
        $this->assertStringContainsString('/reset-password/', $link['url']);
        // Shown once and kept out of the page props, so the browser history never stores it.
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion() ?? ''];
        $shown = $this->get('/personas', $headers)->assertOk();
        $this->assertSame($link['url'], $shown->json('flash.link.url'));
        $this->assertArrayNotHasKey('flash', $shown->json('props'));
        $this->get('/personas', $headers)->assertOk()->assertJsonMissingPath('flash.link');

        parse_str(parse_url($link['url'], PHP_URL_QUERY), $query);
        $token = basename(parse_url($link['url'], PHP_URL_PATH));
        $this->post('/logout');
        $reset = ['token' => $token, 'email' => $query['email'], 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'];
        $this->post('/reset-password', $reset)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('nueva-clave-1', $friend->fresh()->password));
        $this->post('/reset-password', [...$reset, 'password' => 'otra-clave-2', 'password_confirmation' => 'otra-clave-2'])->assertSessionHasErrors('email');
    }

    public function test_without_email_the_forgot_password_page_points_to_the_owner(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);
        config(['mail.default' => 'log']);
        $this->get('/forgot-password')->assertInertia(fn (Assert $page) => $page->where('sendsEmail', false)->etc());
        $this->post('/forgot-password', ['email' => 'ana@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('password_reset_tokens', 0);

        config(['mail.default' => 'smtp']);
        $this->get('/forgot-password')->assertInertia(fn (Assert $page) => $page->where('sendsEmail', true)->etc());
    }

    public function test_the_owner_is_the_installation_owner_not_just_the_first_account(): void
    {
        $first = User::factory()->create();
        $owner = User::factory()->create();
        Installation::put('owner_user_id', (string) $owner->id);
        $this->actingAs($first)->get('/personas')->assertForbidden();
        $this->actingAs($owner)->get('/personas')->assertOk();
    }
}
