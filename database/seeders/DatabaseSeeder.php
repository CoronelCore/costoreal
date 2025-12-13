<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear categorías de gastos
        $this->call(ExpenseCategorySeeder::class);

        // Las cuentas se crean con credenciales propias; nunca se incluyen en las semillas.
    }
}
