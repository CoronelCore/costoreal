<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpanishMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_password_recovery_explain_errors_in_spanish(): void
    {
        $this->assertSame('es', app()->getLocale());
        User::factory()->create(['email' => 'ocupado@example.test']);
        Installation::setRegistrationOpen(true);
        $this->post('/register', ['name' => 'Prueba', 'email' => 'ocupado@example.test', 'password' => 'corta', 'password_confirmation' => 'distinta'])
            ->assertSessionHasErrors([
                'email' => 'Ese correo electrónico ya está registrado.',
                'password' => 'La confirmación de contraseña no coincide.',
            ]);
        $this->post('/forgot-password', ['email' => 'nadie@example.test'])
            ->assertSessionHasErrors(['email' => 'No encontramos una cuenta con ese correo.']);
    }

    public function test_generic_rules_without_their_own_text_read_naturally(): void
    {
        $this->assertSame('Completa nombre.', __('validation.required', ['attribute' => __('validation.attributes.nombre')]));
        $this->assertSame('Contraseña debe tener al menos 8 caracteres.', __('validation.min.string', ['attribute' => 'contraseña', 'min' => 8]));
    }
}
