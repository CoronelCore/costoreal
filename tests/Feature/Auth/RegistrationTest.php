<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private array $newUser = ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1',
        'understood' => true];

    public function test_registration_is_closed_by_default_and_the_sign_in_page_hides_it(): void
    {
        User::factory()->create();
        $this->get('/register')->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Esta instalación no acepta cuentas nuevas. Pide acceso a quien la administra.');
        $this->post('/register', $this->newUser)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', false)->etc());
    }

    public function test_new_users_can_register_once_the_owner_opens_registration(): void
    {
        User::factory()->create(['name' => 'Manuel']);
        Installation::setRegistrationOpen(true);
        $this->get('/register')->assertInertia(fn (Assert $page) => $page->component('Auth/Register')
            ->where('owner', 'Manuel')->where('beta', true));
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', true)->etc());
        $response = $this->post('/register', [...$this->newUser, 'email' => 'Test@Example.com']);

        $this->assertAuthenticated();
        $response->assertRedirect(route('trips.index'))->assertSessionHas('success', 'Tu cuenta quedó lista. Lo que registres aquí lo ves solo tú.');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->get('/trips')->assertInertia(fn (Assert $page) => $page->where('beta.owner', 'Manuel')->etc());
    }

    public function test_open_registration_asks_the_same_as_an_invitation(): void
    {
        User::factory()->create();
        Installation::setRegistrationOpen(true);
        $this->post('/register', [...$this->newUser, 'understood' => false])
            ->assertSessionHasErrors(['understood' => 'Marca la casilla para confirmar que leíste cómo se guardan tus datos.']);
        $this->post('/register', [...$this->newUser, 'password' => 'clave-123', 'password_confirmation' => 'clave-123'])->assertSessionHasErrors('password');
        $this->post('/register', [...$this->newUser, 'password' => 'solo-letras-largas', 'password_confirmation' => 'solo-letras-largas'])->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_only_the_owner_can_open_or_close_registration(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $this->actingAs($guest)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('installation', null)->etc());
        $this->patch('/personas/registro', ['registration_open' => true])->assertForbidden();
        $this->assertFalse(Installation::registrationOpen());

        $this->actingAs($owner)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('installation.people', 2)->etc());
        $this->get('/personas')->assertInertia(fn (Assert $page) => $page->where('registration_open', false)->etc());
        $this->patch('/personas/registro', ['registration_open' => true])->assertRedirect(route('people.index'))
            ->assertSessionHas('success', 'Registro abierto: cualquiera con la dirección puede crear una cuenta.');
        $this->assertTrue(Installation::registrationOpen());
        $this->patch('/personas/registro', ['registration_open' => false])->assertSessionHas('success', 'Registro cerrado: solo entran las personas invitadas.');
        $this->assertFalse(Installation::registrationOpen());
    }
}
