<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InitializeCostoreal extends Command
{
    protected $signature = 'costoreal:initialize';

    protected $description = 'Inicializar un checkout conservando la clave y la base de datos existentes';

    public function handle(): int
    {
        if (! config('app.key') && $this->call('key:generate') !== self::SUCCESS) {
            return self::FAILURE;
        }
        if (config('database.default') === 'sqlite') {
            $file = config('database.connections.sqlite.database');
            if ($file !== ':memory:' && ! is_file($file)) {
                if (! is_dir(dirname($file))) {
                    mkdir(dirname($file), 0750, true);
                }
                if (! touch($file)) {
                    $this->error('No se pudo crear la base SQLite.');

                    return self::FAILURE;
                }
            }
        }

        return $this->call('migrate', ['--force' => true]);
    }
}
