<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener el usuario actual (ID 3 según los logs)
        $user = User::find(3);

        if (!$user) {
            $this->command->error('Usuario con ID 3 no encontrado. Asegúrate de haber ejecutado el DatabaseSeeder primero.');
            return;
        }

        $this->command->info("Creando datos de prueba para: {$user->email}");

        // Crear 4 empresas
        $companies = [];
        $companiesData = [
            ['nombre' => 'Importaciones del Norte', 'rut' => '76.123.456-7', 'color' => '#EF4444', 'icono' => 'truck'],
            ['nombre' => 'Comercial Sur SpA', 'rut' => '77.234.567-8', 'color' => '#3B82F6', 'icono' => 'building-office'],
            ['nombre' => 'Global Trade Chile', 'rut' => '78.345.678-9', 'color' => '#10B981', 'icono' => 'globe-americas'],
            ['nombre' => 'Importex Ltda', 'rut' => '79.456.789-0', 'color' => '#F59E0B', 'icono' => 'shopping-bag'],
        ];

        foreach ($companiesData as $companyData) {
            $companies[] = Company::create([
                'user_id' => $user->id,
                'nombre' => $companyData['nombre'],
                'rut' => $companyData['rut'],
                'color' => $companyData['color'],
                'icono' => $companyData['icono'],
                'descripcion' => 'Empresa dedicada a la importación de productos diversos',
                'activa' => true,
            ]);
        }

        $this->command->info('✓ Empresas creadas: ' . count($companies));

        // Obtener categorías existentes
        $categories = ExpenseCategory::all();
        if ($categories->isEmpty()) {
            $this->command->error('No hay categorías de gastos. Ejecuta ExpenseCategorySeeder primero.');
            return;
        }

        // Crear 8 importaciones variadas
        $tripsData = [
            [
                'nombre' => 'Electrónica China - Marzo 2024',
                'descripcion' => 'Importación de smartphones, tablets y accesorios electrónicos desde Shenzhen',
                'company' => $companies[0],
                'moneda_trabajo' => 'USD',
                'tipo_cambio' => 920.50,
                'fecha_inicio' => '2024-03-01',
                'fecha_fin' => '2024-03-25',
                'estado' => 'finalizado',
            ],
            [
                'nombre' => 'Textiles Brasil - Abril 2024',
                'descripcion' => 'Ropa deportiva y casual de marcas brasileñas',
                'company' => $companies[1],
                'moneda_trabajo' => 'BRL',
                'tipo_cambio' => 185.30,
                'fecha_inicio' => '2024-04-05',
                'fecha_fin' => '2024-04-28',
                'estado' => 'finalizado',
            ],
            [
                'nombre' => 'Juguetes y Regalos - Mayo 2024',
                'descripcion' => 'Importación de juguetes educativos y artículos de regalo',
                'company' => $companies[2],
                'moneda_trabajo' => 'USD',
                'tipo_cambio' => 925.00,
                'fecha_inicio' => '2024-05-10',
                'fecha_fin' => '2024-06-05',
                'estado' => 'finalizado',
            ],
            [
                'nombre' => 'Calzado Perú - Junio 2024',
                'descripcion' => 'Calzado de cuero premium desde Lima',
                'company' => $companies[1],
                'moneda_trabajo' => 'PEN',
                'tipo_cambio' => 245.80,
                'fecha_inicio' => '2024-06-15',
                'fecha_fin' => null,
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Muebles para Hogar - Julio 2024',
                'descripcion' => 'Muebles modernos y accesorios de decoración',
                'company' => $companies[3],
                'moneda_trabajo' => 'USD',
                'tipo_cambio' => 935.75,
                'fecha_inicio' => '2024-07-01',
                'fecha_fin' => null,
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Herramientas Industriales - Agosto 2024',
                'descripcion' => 'Herramientas eléctricas y equipamiento industrial',
                'company' => $companies[0],
                'moneda_trabajo' => 'USD',
                'tipo_cambio' => 940.20,
                'fecha_inicio' => '2024-08-12',
                'fecha_fin' => null,
                'estado' => 'activo',
            ],
            [
                'nombre' => 'Cosmética y Belleza - Septiembre 2024',
                'descripcion' => 'Productos de belleza y cuidado personal importados',
                'company' => $companies[2],
                'moneda_trabajo' => 'USD',
                'tipo_cambio' => 945.50,
                'fecha_inicio' => '2024-09-05',
                'fecha_fin' => '2024-09-30',
                'estado' => 'finalizado',
            ],
            [
                'nombre' => 'Artículos Deportivos - Octubre 2024',
                'descripcion' => 'Equipamiento deportivo y ropa fitness',
                'company' => $companies[3],
                'moneda_trabajo' => 'BRL',
                'tipo_cambio' => 190.45,
                'fecha_inicio' => '2024-10-10',
                'fecha_fin' => null,
                'estado' => 'activo',
            ],
        ];

        foreach ($tripsData as $index => $tripData) {
            $trip = Trip::create([
                'user_id' => $user->id,
                'company_id' => $tripData['company']->id,
                'nombre' => $tripData['nombre'],
                'descripcion' => $tripData['descripcion'],
                'moneda_base' => 'CLP',
                'moneda_trabajo' => $tripData['moneda_trabajo'],
                'tipo_cambio' => $tripData['tipo_cambio'],
                'fecha_inicio' => $tripData['fecha_inicio'],
                'fecha_fin' => $tripData['fecha_fin'],
                'estado' => $tripData['estado'],
            ]);

            // Crear gastos para esta importación
            $this->createExpensesForTrip($trip, $categories);

            // Crear productos para esta importación
            $this->createProductsForTrip($trip);

            $this->command->info("✓ Importación creada: {$trip->nombre}");
        }

        $this->command->info('');
        $this->command->info('✅ Datos de prueba creados exitosamente!');
        $this->command->info('');
        $this->command->info('Resumen:');
        $this->command->info('- ' . count($companies) . ' empresas');
        $this->command->info('- ' . count($tripsData) . ' importaciones');
        $this->command->info('- Múltiples productos y gastos por importación');
    }

    private function createExpensesForTrip(Trip $trip, $categories): void
    {
        $expenseTemplates = [
            // Transporte
            ['concepto' => 'Flete marítimo', 'monto_min' => 1500, 'monto_max' => 3500, 'category' => 'Transporte'],
            ['concepto' => 'Flete terrestre local', 'monto_min' => 200, 'monto_max' => 600, 'category' => 'Transporte'],
            ['concepto' => 'Seguro de carga', 'monto_min' => 300, 'monto_max' => 800, 'category' => 'Transporte'],

            // Aduana
            ['concepto' => 'Derechos aduaneros', 'monto_min' => 500, 'monto_max' => 1200, 'category' => 'Aduana'],
            ['concepto' => 'Agente aduanero', 'monto_min' => 150, 'monto_max' => 400, 'category' => 'Aduana'],
            ['concepto' => 'Certificaciones', 'monto_min' => 100, 'monto_max' => 300, 'category' => 'Aduana'],

            // Almacenamiento
            ['concepto' => 'Bodega puerto', 'monto_min' => 200, 'monto_max' => 500, 'category' => 'Almacenamiento'],
            ['concepto' => 'Almacenaje local', 'monto_min' => 150, 'monto_max' => 400, 'category' => 'Almacenamiento'],

            // Otros
            ['concepto' => 'Trámites legales', 'monto_min' => 100, 'monto_max' => 300, 'category' => 'Legal'],
            ['concepto' => 'Inspección sanitaria', 'monto_min' => 80, 'monto_max' => 200, 'category' => 'Sanitario'],
            ['concepto' => 'Embalaje especial', 'monto_min' => 150, 'monto_max' => 400, 'category' => 'Embalaje'],
            ['concepto' => 'Manipulación de carga', 'monto_min' => 100, 'monto_max' => 250, 'category' => 'Otro'],
        ];

        // Seleccionar entre 6 y 12 gastos aleatorios
        $numExpenses = rand(6, 12);
        $selectedExpenses = array_rand($expenseTemplates, min($numExpenses, count($expenseTemplates)));

        if (!is_array($selectedExpenses)) {
            $selectedExpenses = [$selectedExpenses];
        }

        foreach ($selectedExpenses as $index) {
            $template = $expenseTemplates[$index];
            $category = $categories->firstWhere('nombre', $template['category']);

            if (!$category) {
                continue;
            }

            // 70% en moneda de trabajo, 30% en CLP
            $useWorkCurrency = rand(1, 100) <= 70;
            $moneda = $useWorkCurrency ? $trip->moneda_trabajo : 'CLP';
            $monto = rand($template['monto_min'], $template['monto_max']) + (rand(0, 99) / 100);

            // Si es en moneda de trabajo, el monto es menor
            if ($useWorkCurrency && $trip->moneda_trabajo !== 'CLP') {
                $monto = $monto / 2; // Ajustar para que sea realista en USD, BRL, PEN
            }

            $montoClp = $moneda === 'CLP' ? $monto : $monto * $trip->tipo_cambio;

            Expense::create([
                'trip_id' => $trip->id,
                'expense_category_id' => $category->id,
                'concepto' => $template['concepto'],
                'moneda' => $moneda,
                'monto' => round($monto, 2),
                'monto_clp' => round($montoClp, 2),
            ]);
        }
    }

    private function createProductsForTrip(Trip $trip): void
    {
        $productTemplates = [
            // Electrónica
            ['nombre' => 'Smartphone Galaxy X12', 'cantidad_min' => 50, 'cantidad_max' => 150, 'costo_min' => 250, 'costo_max' => 400],
            ['nombre' => 'Tablet Premium 10"', 'cantidad_min' => 30, 'cantidad_max' => 80, 'costo_min' => 180, 'costo_max' => 320],
            ['nombre' => 'Auriculares Bluetooth Pro', 'cantidad_min' => 100, 'cantidad_max' => 300, 'costo_min' => 25, 'costo_max' => 60],
            ['nombre' => 'Smartwatch Fitness', 'cantidad_min' => 40, 'cantidad_max' => 120, 'costo_min' => 80, 'costo_max' => 150],

            // Textiles
            ['nombre' => 'Camiseta Deportiva Premium', 'cantidad_min' => 200, 'cantidad_max' => 500, 'costo_min' => 8, 'costo_max' => 18],
            ['nombre' => 'Pantalón Jogger Casual', 'cantidad_min' => 150, 'cantidad_max' => 350, 'costo_min' => 12, 'costo_max' => 25],
            ['nombre' => 'Chaqueta Impermeable', 'cantidad_min' => 80, 'cantidad_max' => 200, 'costo_min' => 25, 'costo_max' => 50],

            // Juguetes
            ['nombre' => 'Set de Construcción 500 piezas', 'cantidad_min' => 60, 'cantidad_max' => 150, 'costo_min' => 20, 'costo_max' => 40],
            ['nombre' => 'Muñeca Interactiva', 'cantidad_min' => 80, 'cantidad_max' => 200, 'costo_min' => 30, 'costo_max' => 55],
            ['nombre' => 'Robot Educativo', 'cantidad_min' => 40, 'cantidad_max' => 100, 'costo_min' => 45, 'costo_max' => 85],

            // Calzado
            ['nombre' => 'Zapatos de Cuero Casual', 'cantidad_min' => 100, 'cantidad_max' => 250, 'costo_min' => 35, 'costo_max' => 65],
            ['nombre' => 'Botas de Trabajo', 'cantidad_min' => 80, 'cantidad_max' => 180, 'costo_min' => 40, 'costo_max' => 75],
            ['nombre' => 'Zapatillas Running Pro', 'cantidad_min' => 120, 'cantidad_max' => 280, 'costo_min' => 45, 'costo_max' => 85],

            // Muebles
            ['nombre' => 'Mesa de Centro Moderna', 'cantidad_min' => 20, 'cantidad_max' => 50, 'costo_min' => 60, 'costo_max' => 120],
            ['nombre' => 'Silla Ergonómica', 'cantidad_min' => 50, 'cantidad_max' => 120, 'costo_min' => 40, 'costo_max' => 85],
            ['nombre' => 'Estantería Modular', 'cantidad_min' => 30, 'cantidad_max' => 70, 'costo_min' => 55, 'costo_max' => 110],

            // Herramientas
            ['nombre' => 'Taladro Eléctrico 500W', 'cantidad_min' => 40, 'cantidad_max' => 100, 'costo_min' => 50, 'costo_max' => 95],
            ['nombre' => 'Set de Llaves Métricas', 'cantidad_min' => 80, 'cantidad_max' => 180, 'costo_min' => 25, 'costo_max' => 50],
            ['nombre' => 'Sierra Circular Profesional', 'cantidad_min' => 25, 'cantidad_max' => 60, 'costo_min' => 80, 'costo_max' => 150],
        ];

        // Seleccionar entre 4 y 7 productos aleatorios
        $numProducts = rand(4, 7);
        $selectedProducts = array_rand($productTemplates, min($numProducts, count($productTemplates)));

        if (!is_array($selectedProducts)) {
            $selectedProducts = [$selectedProducts];
        }

        foreach ($selectedProducts as $index) {
            $template = $productTemplates[$index];

            // 80% en moneda de trabajo, 20% en CLP
            $useWorkCurrency = rand(1, 100) <= 80;
            $moneda = $useWorkCurrency ? $trip->moneda_trabajo : 'CLP';
            $cantidad = rand($template['cantidad_min'], $template['cantidad_max']);
            $costoUnitario = rand($template['costo_min'] * 100, $template['costo_max'] * 100) / 100;

            // Si es en moneda de trabajo, ajustar el costo
            if ($useWorkCurrency && $trip->moneda_trabajo !== 'CLP') {
                $costoUnitario = $costoUnitario / 2; // Ajustar para USD, BRL, PEN
            }

            $tc = $moneda === 'CLP' ? 1 : $trip->tipo_cambio;
            $subtotalClp = $costoUnitario * $cantidad * $tc;

            $product = Product::create([
                'trip_id' => $trip->id,
                'nombre' => $template['nombre'],
                'moneda' => $moneda,
                'cantidad' => $cantidad,
                'costo_unitario' => round($costoUnitario, 2),
                'subtotal_clp' => round($subtotalClp, 2),
                'costo_final_unitario_clp' => 0, // Se calculará con el prorrateo
                'costo_final_total_clp' => 0,
            ]);

            // 60% de los productos tendrán precio de venta
            if (rand(1, 100) <= 60 && $trip->estado === 'finalizado') {
                // Precio de venta: entre 1.3x y 2.2x el costo
                $margenMultiplicador = (rand(130, 220) / 100);
                // El precio de venta se calcula después del prorrateo, pero lo estimamos
                $precioVentaEstimado = round($costoUnitario * $tc * $margenMultiplicador, 2);

                $product->update([
                    'precio_venta' => $precioVentaEstimado,
                ]);
            }
        }
    }
}
