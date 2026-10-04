<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentMutationTest extends TestCase
{
    public function test_two_real_sqlite_processes_cannot_overwrite_the_same_revision(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'costoreal-concurrency-');
        $marker = $database.'.marker';
        $workers = [];
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            $user = User::factory()->create();
            $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Inicial', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
            $command = fn ($name) => [PHP_BINARY, base_path('tests/Support/concurrent-mutation.php'), $database, (string) $trip->id, (string) $user->id, $name, $marker];
            $first = new Process($command('Worker A'), base_path(), ['APP_ENV' => 'testing']);
            $first->setTimeout(10)->start();
            $workers[] = $first;
            $deadline = microtime(true) + 5;
            while (! is_file($marker) && $first->isRunning() && microtime(true) < $deadline) {
                usleep(1000);
            }
            $this->assertFileExists($marker, $first->getErrorOutput());
            $second = new Process($command('Worker B'), base_path(), ['APP_ENV' => 'testing']);
            $second->setTimeout(10)->start();
            $workers[] = $second;
            $first->wait();
            $second->wait();
            $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
            $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
            $this->assertSame('applied', $first->getOutput());
            $this->assertSame('conflict', $second->getOutput());
            $this->assertSame('Worker A', $trip->fresh()->nombre);
            $this->assertSame(2, $trip->fresh()->revision);
            $this->assertSame(1, $trip->costRuns()->count());
            $this->assertSame(1, $trip->auditEvents()->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            DB::disconnect('sqlite');
            foreach ([$database, $marker, $database.'-wal', $database.'-shm', $database.'-journal'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
