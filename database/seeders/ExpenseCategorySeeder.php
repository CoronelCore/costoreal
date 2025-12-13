<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['nombre' => 'Transporte', 'icono' => 'truck', 'color' => '#3B82F6'],
            ['nombre' => 'Alojamiento', 'icono' => 'home', 'color' => '#8B5CF6'],
            ['nombre' => 'Alimentación', 'icono' => 'cake', 'color' => '#EF4444'],
            ['nombre' => 'Pasajes Aéreos', 'icono' => 'paper-airplane', 'color' => '#10B981'],
            ['nombre' => 'Taxi/Uber', 'icono' => 'location-marker', 'color' => '#F59E0B'],
            ['nombre' => 'Peajes', 'icono' => 'cash', 'color' => '#6B7280'],
            ['nombre' => 'Combustible', 'icono' => 'lightning-bolt', 'color' => '#EC4899'],
            ['nombre' => 'Otros Gastos', 'icono' => 'dots-horizontal', 'color' => '#14B8A6'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['nombre' => $category['nombre']],
                $category
            );
        }
    }
}
