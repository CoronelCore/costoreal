<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\InvitationController;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Manuel']);
    }

    /** @return string The plain token from the one-time link shown to the owner. */
    private function invite(string $name = 'Ana Pérez', string $email = 'ana@example.test'): string
    {
        $this->actingAs($this->owner)->post('/personas/invitaciones', ['name' => $name, 'email' => $email])
            ->assertRedirect(route('people.index'))->assertSessionHasNoErrors();
        $link = session('inertia.flash_data')['link'];
        $this->assertSame('invitation', $link['kind']);
        $this->assertSame(Invitation::DAYS * 24, $link['hours']);
        $this->post('/logout');

        return basename($link['url']);
    }

    private function account(array $extra = []): array
    {
        return ['name' => 'Ana Pérez', 'email' => 'ana@example.test', 'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1',
            'understood' => true, ...$extra];
    }

    public function test_the_account_is_created_only_after_reading_where_the_data_lives(): void
    {
        $token = $this->invite();
        $this->get("/invitacion/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation.beta', true)->etc());
        $this->post("/invitacion/{$token}", $this->account(['understood' => false]))
            ->assertSessionHasErrors(['understood' => 'Marca la casilla para confirmar que leíste cómo se guardan tus datos.']);
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(Invitation::sole()->accepted_at);
    }

    public function test_invited_accounts_see_the_test_band_until_the_owner_turns_it_off(): void
    {
        $token = $this->invite();
        $this->post("/invitacion/{$token}", $this->account());
        $this->get('/trips')->assertInertia(fn (Assert $page) => $page->where('beta.owner', 'Manuel')->etc());
        $this->patch('/personas/aviso-prueba', ['beta_notice' => false])->assertForbidden();

        $this->actingAs($this->owner)->get('/trips')->assertInertia(fn (Assert $page) => $page->where('beta', null)->etc());
        $this->get('/personas')->assertInertia(fn (Assert $page) => $page->where('beta_notice', true)->etc());
        $this->patch('/personas/aviso-prueba', ['beta_notice' => false])->assertRedirect(route('people.index'));
        $this->assertFalse(Installation::betaNotice());

        $this->actingAs(User::where('email', 'ana@example.test')->sole())->get('/trips')
            ->assertInertia(fn (Assert $page) => $page->where('beta', null)->etc());
    }

    public function test_an_invitation_opens_one_account_while_registration_stays_closed(): void
    {
        $token = $this->invite();
        $this->assertFalse(Installation::registrationOpen());
        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);
        $this->assertDatabaseHas('invitations', ['token_hash' => Invitation::hash($token), 'email' => 'ana@example.test']);

        $this->get("/invitacion/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Invitation')
            ->where('invitation.name', 'Ana Pérez')
            ->where('invitation.email', 'ana@example.test')
            ->where('invitation.owner', 'Manuel'));

        $this->post("/invitacion/{$token}", $this->account())->assertRedirect(route('trips.index'));
        $ana = User::where('email', 'ana@example.test')->sole();
        $this->assertAuthenticatedAs($ana);
        $this->assertFalse(Installation::isOwner($ana));
        $this->assertSame($ana->id, Invitation::sole()->user_id);
        $this->assertNotNull(Invitation::sole()->accepted_at);

        // The same link cannot open a second account.
        $this->post('/logout');
        $this->get("/invitacion/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('invitation', null));
        $this->post("/invitacion/{$token}", $this->account(['email' => 'otra@example.test']))
            ->assertSessionHasErrors(['invitation' => InvitationController::UNUSABLE]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_expired_revoked_and_unknown_links_open_nothing(): void
    {
        $expired = $this->invite('Ana', 'ana@example.test');
        $revoked = $this->invite('Beto', 'beto@example.test');
        $this->actingAs($this->owner)->patch('/personas/invitaciones/'.Invitation::where('email', 'beto@example.test')->value('id').'/anular')
            ->assertRedirect(route('people.index'));
        $this->get('/personas')->assertInertia(fn (Assert $page) => $page->has('invitations', 1)->where('invitations.0.email', 'ana@example.test')->where('invitations.0.status', 'pending'));
        $this->post('/logout');

        $this->travel(Invitation::DAYS + 1)->days();
        foreach ([$expired, $revoked, 'enlace-inventado'] as $i => $token) {
            $this->get("/invitacion/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('invitation', null));
            $this->post("/invitacion/{$token}", $this->account(['email' => "persona{$i}@example.test"]))
                ->assertSessionHasErrors(['invitation' => InvitationController::UNUSABLE]);
        }
        $this->assertDatabaseCount('users', 1);
        $this->actingAs($this->owner)->get('/personas')->assertInertia(fn (Assert $page) => $page->where('invitations.0.status', 'expired'));
    }

    public function test_an_email_typed_with_capitals_is_saved_in_lowercase(): void
    {
        $this->actingAs($this->owner)->post('/personas/invitaciones', ['name' => 'Ana', 'email' => ' Ana@Example.TEST '])->assertSessionHasNoErrors();
        $this->assertSame('ana@example.test', Invitation::sole()->email);
        $token = basename(session('inertia.flash_data')['link']['url']);
        $this->post('/logout');
        $this->post("/invitacion/{$token}", $this->account(['email' => 'Ana@Example.test']))->assertRedirect(route('trips.index'));
        $this->assertDatabaseHas('users', ['email' => 'ana@example.test']);
    }

    public function test_the_new_account_needs_a_strong_password_and_an_unused_email(): void
    {
        $token = $this->invite();
        $this->post("/invitacion/{$token}", $this->account(['password' => 'corta', 'password_confirmation' => 'corta']))->assertSessionHasErrors('password');
        $this->post("/invitacion/{$token}", $this->account(['email' => $this->owner->email]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(Invitation::sole()->accepted_at);
    }

    public function test_only_the_owner_invites_and_never_twice_to_an_existing_account(): void
    {
        $friend = User::factory()->create(['email' => 'ana@example.test']);
        $this->actingAs($friend)->post('/personas/invitaciones', ['name' => 'Beto', 'email' => 'beto@example.test'])->assertForbidden();
        $this->actingAs($this->owner)->post('/personas/invitaciones', ['name' => 'Ana', 'email' => 'ana@example.test'])
            ->assertSessionHasErrors(['email' => 'Ya hay una cuenta con ese correo en esta instalación.']);
        $this->assertDatabaseCount('invitations', 0);
    }
}
