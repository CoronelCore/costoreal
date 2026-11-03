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

    private array $newUser = ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password'];

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
        User::factory()->create();
        Installation::setRegistrationOpen(true);
        $this->get('/register')->assertStatus(200);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', true)->etc());
        $response = $this->post('/register', $this->newUser);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
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
