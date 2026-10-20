<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException('Usa php artisan costoreal:demo CORREO en una instalación vacía con una sola cuenta. Este seeder antiguo no carga datos.');
    }
}
