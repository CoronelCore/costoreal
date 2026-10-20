<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Backups\DatabaseCopy;
use App\Modules\Demo\CreateDemoData;
use App\Support\EnvFile;
use App\Support\Installation;
use CostoRealFirstRun;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use Throwable;

/** Browser installation and updates for hostings without a terminal, such as Hostinger's Single plan. */
class InstallerController extends Controller
{
    public function __construct()
    {
        require_once base_path('bootstrap/first-run.php');
    }

    public function show(Request $request): Response
    {
        if (CostoRealFirstRun::readCode(base_path()) === null) {
            CostoRealFirstRun::writeCode(base_path());
        }

        return Inertia::render('Install/Index', [
            'php' => PHP_VERSION,
            'https' => $request->isSecure(),
            'sqlite' => extension_loaded('pdo_sqlite'),
            'mysql' => extension_loaded('pdo_mysql'),
            'codeFile' => 'costoreal/'.CostoRealFirstRun::CODE_FILE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:20',
            'database' => 'required|in:sqlite,mysql',
            'db_host' => 'required_if:database,mysql|nullable|string|max:255',
            'db_port' => 'required_if:database,mysql|nullable|integer|between:1,65535',
            'db_name' => 'required_if:database,mysql|nullable|string|max:64',
            'db_user' => 'required_if:database,mysql|nullable|string|max:64',
            'db_password' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'demo' => 'boolean',
        ], [
            'database.in' => 'Elige SQLite o MySQL.',
            'db_host.required_if' => 'Indica el servidor de MySQL (en Hostinger suele ser localhost).',
            'db_name.required_if' => 'Indica el nombre de la base de datos que creaste en hPanel.',
            'db_user.required_if' => 'Indica el usuario de la base de datos que creaste en hPanel.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.letters' => 'La contraseña debe tener al menos una letra.',
            'password.numbers' => 'La contraseña debe tener al menos un número.',
        ]);
        $expected = CostoRealFirstRun::readCode(base_path());
        if ($expected === null || ! hash_equals($expected, self::normalizeCode($data['code']))) {
            throw ValidationException::withMessages(['code' => 'El código no coincide con el del archivo '.CostoRealFirstRun::CODE_FILE.'.']);
        }
        if ($data['database'] === 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            throw ValidationException::withMessages(['database' => 'Este hosting no tiene SQLite activado. Elige MySQL o activa pdo_sqlite en hPanel.']);
        }
        if ($data['database'] === 'mysql') {
            $this->useMysql($data);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            (new ExpenseCategorySeeder)->run();
            $owner = DB::transaction(function () use ($data) {
                if (User::query()->exists()) {
                    throw ValidationException::withMessages(['install' => 'CostoReal ya estaba instalado. Entra con tu cuenta.']);
                }
                $owner = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
                Installation::markInstalled($owner);

                return $owner;
            });
            if ($request->boolean('demo')) {
                app(CreateDemoData::class)->execute($owner);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('La instalación de CostoReal no terminó.', ['exception' => $exception]);
            throw ValidationException::withMessages(['install' => 'La instalación no terminó: '.$exception->getMessage()]);
        }
        @unlink(base_path(CostoRealFirstRun::CODE_FILE));
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('trips.index')->with('success', 'CostoReal quedó instalado. Esta cuenta es la dueña de la instalación.');
    }

    public function update(Request $request): Response
    {
        return Inertia::render('Install/Update', [
            'pending' => count(Installation::pendingMigrations()),
            'signedIn' => $request->user() !== null,
            'isOwner' => Installation::isOwner($request->user()),
        ]);
    }

    /** The owner applies an uploaded version; the SQLite database is copied first so it can be restored. */
    public function apply(Request $request, DatabaseCopy $copy): RedirectResponse
    {
        abort_unless(Installation::isOwner($request->user()), 403, 'Solo la cuenta dueña puede aplicar la actualización.');
        $backup = $copy->beforeUpdate();
        Artisan::call('migrate', ['--force' => true]);
        Installation::put('schema', Installation::latestMigration());

        return redirect()->route('trips.index')->with('success', $backup
            ? 'CostoReal quedó actualizado. Antes se guardó una copia de la base de datos en '.$backup.'.'
            : 'CostoReal quedó actualizado.');
    }

    public static function normalizeCode(string $code): string
    {
        $plain = preg_replace('/[^A-Z0-9]/', '', strtoupper($code));

        return strlen($plain) === 8 ? substr($plain, 0, 4).'-'.substr($plain, 4) : $plain;
    }

    /** Checks the MySQL credentials, saves them privately and switches this request to them. */
    private function useMysql(array $data): void
    {
        $settings = ['host' => $data['db_host'], 'port' => (int) $data['db_port'], 'database' => $data['db_name'],
            'username' => $data['db_user'], 'password' => (string) ($data['db_password'] ?? '')];
        try {
            new PDO("mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['database']};charset=utf8mb4",
                $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['db_host' => 'No se pudo conectar a MySQL con esos datos. Revisa el nombre de la base, el usuario y la contraseña que creaste en hPanel.']);
        }
        EnvFile::set(base_path('.env'), ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $settings['host'], 'DB_PORT' => $settings['port'],
            'DB_DATABASE' => $settings['database'], 'DB_USERNAME' => $settings['username'], 'DB_PASSWORD' => $settings['password']]);
        config(['database.default' => 'mysql', 'database.connections.mysql' => [...config('database.connections.mysql'), ...$settings]]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
    }
}
