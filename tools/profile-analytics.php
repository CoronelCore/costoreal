<?php

use App\Models\Trip;
use App\Models\User;
use App\Modules\Access\AccessContext;
use App\Modules\Analytics\AnalyticsQuery;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// A fresh synthetic SQLite database is mandatory; never reads or seeds the configured database.
$directory = sys_get_temp_dir().'/costoreal-analytics-'.bin2hex(random_bytes(8));
if (! mkdir($directory, 0700)) {
    throw new RuntimeException('No se pudo crear la carpeta temporal.');
}
$database = $directory.'/fixture.sqlite';
touch($database);
chmod($database, 0600);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$database);
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('BCRYPT_ROUNDS=4');

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    // Also overrides a previously cached configuration before the first migration/query.
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
    DB::purge('sqlite');
    if (DB::connection()->getDatabaseName() !== $database) {
        throw new RuntimeException('El perfil solo admite su base temporal recién creada.');
    }
    Artisan::call('migrate', ['--force' => true]);
    $user = User::factory()->create(['name' => 'Fixture de rendimiento', 'email' => 'performance@example.test']);
    DB::transaction(function () use ($user): void {
        for ($i = 0; $i < 200; $i++) {
            $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Volumen '.($i + 1), 'moneda_base' => 'CLP',
                'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo',
                'fecha_inicio' => (new DateTimeImmutable('2026-01-01'))->modify("+$i days")->format('Y-m-d')]);
            $products = [];
            for ($p = 0; $p < 1000; $p++) {
                $products[] = ['trip_id' => $trip->id, 'nombre' => 'Línea '.$p, 'moneda' => 'CLP', 'cantidad' => 1,
                    'costo_unitario' => '10.10', 'subtotal_clp' => '10.10', 'gastos_prorrateados_clp' => '0.10',
                    'costo_final_total_clp' => '10.20', 'costo_final_unitario_clp' => '10.20', 'precio_venta' => $p % 2 ? '11.50' : null];
            }
            foreach (array_chunk($products, 100) as $chunk) {
                DB::table('products')->insert($chunk);
            }
            $expenses = [];
            for ($e = 0; $e < 500; $e++) {
                $expenses[] = ['trip_id' => $trip->id, 'concepto' => 'Gasto '.$e, 'moneda' => 'CLP',
                    'monto' => '0.20', 'monto_clp' => '0.20', 'created_at' => '2026-01-01 12:00:00'];
            }
            foreach (array_chunk($expenses, 100) as $chunk) {
                DB::table('expenses')->insert($chunk);
            }
        }
    });
    $rows = [];
    foreach ([1000, 100, 10] as $lines) {
        DB::table('products')->whereRaw('((id - 1) % 1000) >= ?', [$lines])->delete();
        DB::table('expenses')->whereRaw('((id - 1) % 500) >= ?', [intdiv($lines, 2)])->delete();
        foreach ([1, 20, 200] as $count) {
            $filters = ['to' => (new DateTimeImmutable('2026-01-01'))->modify('+'.($count - 1).' days')->format('Y-m-d')];
            $request = Request::create('http://localhost/analytics', 'GET', $filters);
            $expected = (string) BigDecimal::of('10.20')->multipliedBy($lines)->multipliedBy($count);
            $samples = [];
            for ($sample = 0; $sample < 3; $sample++) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                $start = hrtime(true);
                $data = (new AnalyticsQuery)->read(new AccessContext($user, null), $filters, $request);
                $elapsed = (hrtime(true) - $start) / 1e6;
                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();
                if ($data['metrics']['total'] !== $expected || $data['projection']['lines'] !== $count * $lines
                    || $data['projection']['priced_lines'] !== intdiv($count * $lines, 2) || $data['metrics']['unassigned'] !== '0.00') {
                    throw new RuntimeException('Los totales del perfil no concilian.');
                }
                $samples[] = ['ms' => round($elapsed, 2), 'queries' => $queries, 'response_bytes' => strlen(json_encode($data, JSON_THROW_ON_ERROR))];
            }
            $rows[] = ['imports' => $count, 'lines_per_import' => $lines, 'total' => $expected, 'reconciled' => true, 'samples' => $samples];
        }
    }
    echo json_encode(['scope' => 'AnalyticsQuery; tres muestras en un proceso; excluye bootstrap y siembra; SQLite local y datos sintéticos',
        'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::disconnect('sqlite');
    foreach ([$database, $database.'-wal', $database.'-shm', $database.'-journal'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($directory);
}
