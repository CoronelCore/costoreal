<?php

namespace Database\Seeders;

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

        // Accounts are created through registration; production seeding never
        // installs a publicly known credential.
    }
}
