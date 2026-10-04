<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecureSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_requires_strong_secret_and_never_overwrites(): void
    {
        $this->artisan('costoreal:create-user', ['--email' => 'owner@example.test', '--name' => 'Owner'])->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Strong!Password123')->assertSuccessful();
        $u = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Strong!Password123', $u->password));
        $this->artisan('costoreal:create-user', ['--email' => 'owner@example.test', '--name' => 'Other'])->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Different!Password123')->assertFailed();
        $this->assertSame(1, User::count());
        $this->assertSame('Owner', $u->fresh()->name);
    }

    public function test_default_seeder_creates_no_example_accounts(): void
    {
        $this->seed();
        $this->assertSame(0, User::count());
    }
}
