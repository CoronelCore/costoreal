<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestForgeryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the real middleware policy instead of its automatic test bypass.
        $this->app->bind(PreventRequestForgery::class, fn () => new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_unverified_writes_require_a_token_and_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'password'];
        $this->post('/login', $credentials)->assertStatus(419);
        foreach (['cross-site', 'same-site'] as $origin) {
            $this->withHeader('Sec-Fetch-Site', $origin)->post('/login', $credentials)->assertStatus(419);
            $this->assertGuest();
        }
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_token_fallback_authenticates_and_protects_logout_when_origin_header_is_unavailable(): void
    {
        $user = User::factory()->create();
        $token = str_repeat('t', 40);
        $this->withSession(['_token' => $token])->post('/login', ['email' => $user->email, 'password' => 'password', '_token' => $token])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout', ['_token' => session()->token()])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_encrypted_xsrf_cookie_header_remains_compatible_with_json_clients(): void
    {
        $token = str_repeat('x', 40);
        $response = $this->withSession(['_token' => $token])->get('/login')->assertOk();
        $cookie = $response->getCookie('XSRF-TOKEN', false);
        $this->assertNotNull($cookie);
        $this->withHeader('X-XSRF-TOKEN', $cookie->getValue())->postJson('/login', ['email' => 'absent@example.test', 'password' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_framework_same_origin_policy_accepts_browser_writes_without_csrf_exemptions(): void
    {
        $user = User::factory()->create();
        $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
