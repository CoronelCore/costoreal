<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Trip;
use App\Models\User;
use App\Support\Installation;
use CostoRealFirstRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private array $account = ['name' => 'Manuel', 'email' => 'dueno@example.test', 'password' => 'clave-segura-2026', 'password_confirmation' => 'clave-segura-2026'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['costoreal.installer' => true]);
        require_once base_path('bootstrap/first-run.php');
    }

    protected function tearDown(): void
    {
        @unlink(base_path(CostoRealFirstRun::CODE_FILE));
        @unlink(database_path('migrations/2099_01_01_000000_probe_pending_update.php'));
        parent::tearDown();
    }

    public function test_a_fresh_copy_sends_every_page_to_the_installer_with_a_one_time_code(): void
    {
        $this->get('/trips')->assertRedirect(route('installer.show'));
        $this->get('/login')->assertRedirect(route('installer.show'));
        $this->get('/instalar')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Install/Index')
            ->where('codeFile', 'costoreal/CODIGO-INSTALACION.txt')->where('sqlite', true));
        $this->assertNotNull(CostoRealFirstRun::readCode(base_path()));
    }

    public function test_a_wrong_code_or_weak_password_installs_nothing(): void
    {
        $this->get('/instalar');
        $this->post('/instalar', [...$this->account, 'code' => 'AAAA-AAAA', 'database' => 'sqlite'])
            ->assertSessionHasErrors(['code' => 'El código no coincide con el del archivo CODIGO-INSTALACION.txt.']);
        $this->post('/instalar', [...$this->account, 'password' => 'corta', 'password_confirmation' => 'corta', 'code' => CostoRealFirstRun::readCode(base_path()), 'database' => 'sqlite'])
            ->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 10 caracteres.']);
        $this->assertSame(0, User::count());
        $this->assertFalse(Installation::installed());
    }

    public function test_installing_creates_the_owner_categories_and_optional_demo_then_locks_itself(): void
    {
        $this->get('/instalar');
        $code = strtolower(str_replace('-', ' ', CostoRealFirstRun::readCode(base_path())));
        $this->post('/instalar', [...$this->account, 'code' => $code, 'database' => 'sqlite', 'demo' => true])
            ->assertSessionHasNoErrors()->assertRedirect(route('trips.index'));
        $owner = User::sole();
        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(Installation::isOwner($owner));
        $this->assertNotNull(Installation::setting('installed_at'));
        $this->assertFalse(Installation::registrationOpen());
        $this->assertSame(8, ExpenseCategory::whereNull('user_id')->count());
        $this->assertSame(2, Trip::count());
        $this->assertFileDoesNotExist(base_path(CostoRealFirstRun::CODE_FILE));
        $this->get('/instalar')->assertRedirect(route('login'));
        $this->post('/instalar', [...$this->account, 'code' => $code, 'database' => 'sqlite'])->assertRedirect(route('login'));
        $this->assertSame(1, User::count());
    }

    public function test_an_uploaded_version_waits_until_the_owner_applies_its_migrations(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Installation::markInstalled($owner);
        file_put_contents(database_path('migrations/2099_01_01_000000_probe_pending_update.php'), <<<'PHP'
            <?php
            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            return new class extends Migration {
                public function up(): void { Schema::create('update_probe', fn (Blueprint $table) => $table->id()); }
                public function down(): void { Schema::dropIfExists('update_probe'); }
            };
            PHP);
        $this->get('/trips')->assertRedirect(route('installer.update'));
        $this->get('/login')->assertOk();
        $this->get('/instalar/actualizar')->assertInertia(fn (Assert $page) => $page->component('Install/Update')->where('pending', 1)->where('signedIn', false));
        $this->actingAs($other)->post('/instalar/actualizar')->assertForbidden();
        $this->assertFalse(Schema::hasTable('update_probe'));
        $this->actingAs($owner)->post('/instalar/actualizar')->assertRedirect(route('trips.index'));
        $this->assertTrue(Schema::hasTable('update_probe'));
        $this->assertSame('2099_01_01_000000_probe_pending_update', Installation::setting('schema'));
        $this->get('/trips')->assertOk();
        $this->get('/instalar/actualizar')->assertRedirect(route('login'));
    }
}
