<?php

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Called only by profile-http.py; accepts a newly created private temporary directory.
$directory = realpath($argv[1] ?? '');
$lines = (int) ($argv[2] ?? 0);
$password = getenv('COSTOREAL_PROFILE_PASSWORD');
if (! $directory || realpath(dirname($directory)) !== realpath(sys_get_temp_dir())
    || ! str_starts_with(basename($directory), 'costoreal-http-profile-')
    || ! in_array($lines, [10, 100, 1000], true) || ! is_string($password) || strlen($password) < 24) {
    throw new RuntimeException('El fixture exige una carpeta temporal privada y credenciales sintéticas.');
}
$database = $directory.'/fixture-'.$lines.'.sqlite';
$handle = fopen($database, 'x');
if (! $handle) {
    throw new RuntimeException('El fixture nunca reutiliza una base existente.');
}
fclose($handle);
chmod($database, 0600);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$database);
putenv('CACHE_STORE=array');
putenv('BCRYPT_ROUNDS=4');
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
DB::purge('sqlite');
if (DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('El fixture solo admite su propia SQLite temporal.');
}
Artisan::call('migrate', ['--force' => true]);
$user = User::factory()->create(['name' => 'Fixture de rendimiento', 'email' => 'performance@example.test', 'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4])]);
DB::transaction(function () use ($user, $lines): void {
    for ($i = 0; $i < 200; $i++) {
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Volumen '.($i + 1), 'moneda_base' => 'CLP',
            'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo',
            'fecha_inicio' => (new DateTimeImmutable('2026-01-01'))->modify("+$i days")->format('Y-m-d')]);
        $products = [];
        for ($p = 0; $p < $lines; $p++) {
            $products[] = ['trip_id' => $trip->id, 'nombre' => 'Línea '.$p, 'moneda' => 'CLP', 'cantidad' => 1,
                'costo_unitario' => '10.10', 'subtotal_clp' => '10.10', 'gastos_prorrateados_clp' => '0.10',
                'costo_final_total_clp' => '10.20', 'costo_final_unitario_clp' => '10.20', 'precio_venta' => $p % 2 ? '11.50' : null];
        }
        foreach (array_chunk($products, 100) as $chunk) {
            DB::table('products')->insert($chunk);
        }
        $expenses = [];
        for ($e = 0; $e < intdiv($lines, 2); $e++) {
            $expenses[] = ['trip_id' => $trip->id, 'concepto' => 'Gasto '.$e, 'moneda' => 'CLP',
                'monto' => '0.20', 'monto_clp' => '0.20', 'created_at' => '2026-01-01 12:00:00'];
        }
        foreach (array_chunk($expenses, 100) as $chunk) {
            DB::table('expenses')->insert($chunk);
        }
    }
});

$company = Company::create(['user_id' => $user->id, 'nombre' => 'Empresa sintética de volumen', 'activa' => true]);
DB::table('trips')->update(['company_id' => $company->id]);
$trip = Trip::orderBy('id')->firstOrFail()->load(['products', 'expenses', 'company']);
$records = [];
for ($i = 1; $i <= 60; $i++) {
    $snapshot = $trip->toArray();
    $snapshot['revision'] = $i;
    $records[] = ['trip_id' => $trip->id, 'revision' => $i, 'algorithm' => 'synthetic-read-only', 'reason' => $i === 1 ? 'close' : 'profile-fixture',
        'actor_id' => $user->id, 'input' => '[]', 'result' => '[]', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        'input_hash' => hash('sha256', 'synthetic-'.$i), 'created_at' => '2026-01-01 12:00:00'];
}
foreach (array_chunk($records, 5) as $chunk) {
    DB::table('cost_runs')->insert($chunk);
}
echo json_encode(['database' => $database, 'lines' => $lines, 'trip_id' => $trip->id, 'company_id' => $company->id,
    'trips' => 200, 'products' => 200 * $lines, 'expenses' => 100 * $lines, 'history_runs' => 60], JSON_THROW_ON_ERROR).PHP_EOL;
