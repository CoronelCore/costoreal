<?php

// Owns one new SQLite in the profiler's private temporary directory; never seeds a live DB.
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$directory = realpath($argv[1] ?? '');
$lines = (int) ($argv[2] ?? 0);
$mode = $argv[3] ?? '';
$password = getenv('COSTOREAL_PROFILE_PASSWORD');
if (! $directory || realpath(dirname($directory)) !== realpath(sys_get_temp_dir())
    || ! str_starts_with(basename($directory), 'costoreal-http-write-profile-')
    || ! in_array($lines, [10, 100, 1000], true) || ! in_array($mode, ['same', 'varied'], true)
    || ! is_string($password) || strlen($password) < 24
    || file_exists(dirname(__DIR__).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('El fixture exige carpeta temporal propia, credenciales sintéticas y configuración sin cache.');
}
$database = $directory.'/fixture-'.$lines.'-'.$mode.'.sqlite';
$handle = fopen($database, 'x');
if (! $handle) {
    throw new RuntimeException('El fixture nunca reutiliza una base existente.');
}
fclose($handle);
chmod($database, 0600);
putenv('DB_URL');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$database);
putenv('CACHE_STORE=array');
putenv('BCRYPT_ROUNDS=4');
putenv('COSTOREAL_PRIVATE_STORAGE_ROOT='.$directory.'/private');
putenv('COSTOREAL_PUBLIC_STORAGE_ROOT='.$directory.'/public');
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('La conexión no pertenece al fixture.');
}
Artisan::call('migrate', ['--force' => true]);
$user = User::factory()->create(['email' => 'performance@example.test', 'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4])]);
$trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Escritura sintética', 'moneda_base' => 'CLP',
    'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
foreach (array_chunk(range(1, $lines), 100) as $numbers) {
    DB::table('products')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'nombre' => 'Línea '.$i,
        'cantidad' => 1, 'moneda' => 'CLP', 'costo_unitario' => '10', 'subtotal_clp' => '10',
        'costo_final_total_clp' => '10', 'costo_final_unitario_clp' => '10', 'rate_mode' => 'estimated',
        'rate_source' => 'trip-reference'], $numbers));
}
$amounts = array_map(fn ($i) => $mode === 'varied' ? ($i % 13) + 1 : 1, range(1, intdiv($lines, 2)));
DB::table('expenses')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'concepto' => 'Gasto '.$i,
    'moneda' => 'CLP', 'monto' => (string) $amounts[$i - 1], 'monto_clp' => (string) $amounts[$i - 1],
    'rate_mode' => 'estimated', 'rate_source' => 'trip-reference'], range(1, intdiv($lines, 2))));
echo json_encode(['database' => $database, 'trip_id' => $trip->id,
    'expense_id' => DB::table('expenses')->min('id'), 'update_amount' => $amounts[0] + 1,
    'expected_total' => $lines * 10 + array_sum($amounts) + 1,
    'allocation_count_per_run' => $lines * intdiv($lines, 2)], JSON_THROW_ON_ERROR).PHP_EOL;
