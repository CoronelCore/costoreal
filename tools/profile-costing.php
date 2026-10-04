<?php

// Always owns a fresh private temporary DB. Never accepts a path to an existing database.
use App\Models\Trip;
use App\Models\User;
use App\Modules\Costing\Reconciliation\CompareStoredCosts;
use App\Modules\Importations\Actions\CloseImportation;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Importations\Review\ReviewImportation;
use App\Modules\Observability\RequestMetrics;
use App\Modules\Scenarios\SimulateCost;
use App\Services\ProrrateoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReferenceCostEngine;

require __DIR__.'/../vendor/autoload.php';
$n = (int) ($argv[1] ?? 1000);
if (! in_array($n, [10, 100, 1000], true) || file_exists(__DIR__.'/../bootstrap/cache/config.php')) {
    fwrite(STDERR, "Usa 10, 100 o 1000 líneas y un checkout sin config cache.\n");
    exit(1);
}
$directory = sys_get_temp_dir().'/costoreal-cost-profile-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$database = $directory.'/fixture.sqlite';
fclose(fopen($database, 'x'));
chmod($database, 0600);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$database);
putenv('CACHE_STORE=array');
putenv('BCRYPT_ROUNDS=4');
putenv('COSTOREAL_PRIVATE_STORAGE_ROOT='.$directory.'/private');
putenv('COSTOREAL_PUBLIC_STORAGE_ROOT='.$directory.'/public');
$report = ['scope' => 'Services and atomic actions in fresh private SQLite; PHP 128 MiB. Not HTTP/p95. Each operation resets the PHP peak counter, retaining its process baseline.',
    'lines' => $n, 'expenses' => intdiv($n, 2), 'memory_limit' => ini_get('memory_limit'), 'rows' => []];
function expect(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
}
$exitCode = 0;
try {
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    expect(DB::connection()->getDatabaseName() === $database, 'Profile DB must be the new temporary file.');
    Artisan::call('migrate:fresh', ['--force' => true]);
    $user = User::factory()->create();
    auth()->login($user);
    $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Costeo sintético', 'moneda_base' => 'CLP',
        'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
    foreach (array_chunk(range(1, $n), 100) as $numbers) {
        DB::table('products')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'nombre' => 'Línea '.$i,
            'cantidad' => 1, 'moneda' => 'CLP', 'costo_unitario' => '10', 'subtotal_clp' => '10',
            'costo_final_total_clp' => '10', 'costo_final_unitario_clp' => '10', 'rate_mode' => 'estimated', 'rate_source' => 'trip-reference'], $numbers));
    }
    DB::table('expenses')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'concepto' => 'Gasto '.$i,
        'moneda' => 'CLP', 'monto' => '1', 'monto_clp' => '1', 'rate_mode' => 'estimated', 'rate_source' => 'trip-reference'], range(1, intdiv($n, 2))));
    $trip = $trip->fresh();
    $costing = app(ProrrateoService::class);
    $measure = function (string $name, callable $operation) use (&$report) {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $metrics = app(RequestMetrics::class);
        $metrics->begin();
        $value = $operation();
        $sample = ['operation' => $name, ...$metrics->finish()];
        $report['rows'][] = $sample;
        fwrite(STDERR, json_encode($sample, JSON_THROW_ON_ERROR)."\n");

        return $value;
    };
    $preview = $measure('complete_engine_preview', fn () => $costing->preview($trip));
    expect(count($preview->allocations) === $n * intdiv($n, 2), 'Every allocation must be retained.');
    $expectedTotal = (string) ($n * 10 + intdiv($n, 2));
    expect($preview->total === $expectedTotal && $preview->unassigned === '0', 'Preview totals must reconcile.');
    $actualHash = hash_init('sha256');
    foreach ($preview->allocations as $row) {
        hash_update($actualHash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
    }
    $traceHash = hash_final($actualHash);
    // The frozen v1 engine is evaluated one expense at a time to keep this independent check bounded.
    $lines = $trip->products->map(fn ($p) => ['id' => $p->id, 'quantity' => $p->cantidad, 'unit_amount' => $p->costo_unitario, 'rate' => '1'])->all();
    $referenceHash = hash_init('sha256');
    foreach ($trip->expenses as $expense) {
        $reference = (new ReferenceCostEngine)->calculate($lines, [['id' => $expense->id, 'amount_clp' => '1', 'method' => 'value']]);
        foreach ($reference->allocations as $row) {
            hash_update($referenceHash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
        }
    }
    expect($traceHash === hash_final($referenceHash), 'All v1 trace fields must match the frozen engine.');
    unset($preview, $reference, $lines);
    $measure('atomic_mutation_and_trace_recording', fn () => app(MutateTrip::class)->execute($trip,
        fn ($current) => $current->products()->orderBy('id')->first()->update(['nombre' => 'Línea revisada']),
        'profile_update', expectedRevision: 1));
    $trip = $trip->fresh();
    $run = $trip->costRuns()->firstOrFail();
    expect($trip->revision === 2 && $run->result['total'] === $expectedTotal, 'Mutation revision and totals must reconcile.');
    expect(! isset($run->result['allocations']) && $run->result['allocation_count'] === $n * intdiv($n, 2), 'New JSON must reference complete trace rows.');
    $persistedHash = hash_init('sha256');
    foreach (DB::table('cost_allocations')->where('cost_run_id', $run->id)->orderBy('expense_id')->orderBy('product_id')->cursor() as $row) {
        hash_update($persistedHash, json_encode(['expense_id' => $row->expense_id, 'product_id' => $row->product_id,
            'amount' => $row->amount, 'method' => $row->method, 'trace' => json_decode($row->trace, true, flags: JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR)."\n");
    }
    expect($traceHash === hash_final($persistedHash), 'Every persisted allocation must match the engine.');
    $original = $run->getRawOriginal();
    $comparison = $measure('stored_cost_comparison', fn () => app(CompareStoredCosts::class)->execute($trip->fresh()));
    expect(count($comparison['comparison']['differences']) === 0, 'Stored cost comparison must have no differences.');
    $review = $measure('importation_review', fn () => app(ReviewImportation::class)->execute($trip->fresh()));
    expect($review['can_close'] && $review['calculated_total'] === $expectedTotal, 'Review must allow the reconciled close.');
    $scenario = $measure('scenario_simulation', fn () => app(SimulateCost::class)->execute($trip->fresh(), ['expense_variation_percent' => '100']));
    expect($scenario['result']['after']['cost'] === (string) ($n * 10 + $n), 'Scenario total must reconcile.');
    expect($trip->fresh()->revision === 2 && $trip->costRuns()->count() === 1, 'Simulation must not write a real run.');
    unset($scenario, $comparison, $review);
    $measure('atomic_close_and_trace_recording', fn () => app(CloseImportation::class)->execute($trip->fresh(), 2));
    expect($trip->fresh()->estado === 'finalizado' && $trip->fresh()->revision === 3, 'Close must commit its state/revision.');
    expect($trip->costRuns()->count() === 2 && $run->fresh()->getRawOriginal() === $original, 'Close must retain the original immutable run.');
    expect(DB::table('cost_allocations')->count() === 2 * $n * intdiv($n, 2), 'Both complete allocation sets must persist.');
    $inserts = 0;
    $inject = false;
    DB::listen(function ($query) use (&$inserts, &$inject) {
        if ($inject && str_contains($query->sql, 'insert into "cost_allocations"') && ++$inserts === 2) {
            $inject = false;
            throw new RuntimeException('Synthetic second-batch rollback');
        }
    });
    // Larger fixtures cross multiple batches; inject after the second batch and prove no partial run.
    if ($n === 1000) {
        $inject = true;
        try {
            app(MutateTrip::class)->execute($trip->fresh(), fn ($current) => $current->update(['nombre' => 'Debe revertirse']),
                'profile_rollback', expectedRevision: 3, ability: 'reopen');
            throw new RuntimeException('Failure injection was not reached.');
        } catch (RuntimeException $error) {
            expect($error->getMessage() === 'Synthetic second-batch rollback', 'Only the injected failure may be caught.');
        }
        expect($trip->fresh()->nombre === 'Costeo sintético' && $trip->fresh()->revision === 3, 'Rollback must restore name/revision.');
        expect($trip->costRuns()->count() === 2 && DB::table('cost_allocations')->count() === 1000000, 'Rollback must leave no partial allocations/run.');
    }
    $report['reconciled'] = true;
    $report['allocation_sha256'] = $traceHash;
    $report['all_reference_and_persisted_traces_equal'] = true;
    $report['rollback_second_batch'] = $n === 1000;
    $report['integrity'] = DB::select('PRAGMA integrity_check')[0]->integrity_check;
    $report['foreign_key_violations'] = count(DB::select('PRAGMA foreign_key_check'));
    $report['temporary_db_bytes'] = filesize($database);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['failed' => true, 'error_class' => $error::class,
        'reason' => $error instanceof QueryException ? 'Database operation failed in the synthetic profile.' : substr($error->getMessage(), 0, 200)], JSON_THROW_ON_ERROR)."\n");
    $exitCode = 1;
} finally {
    if (isset($app)) {
        DB::disconnect();
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}

exit($exitCode);
