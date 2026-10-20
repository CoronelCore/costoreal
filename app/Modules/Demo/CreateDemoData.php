<?php

namespace App\Modules\Demo;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\CreateImportation;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Two fictional purchases: an import in USD and a shopping trip to Brazil in BRL. */
final class CreateDemoData
{
    private const SOURCE = 'Referencia ficticia de demostración';

    public function __construct(private CreateImportation $create, private MutateTrip $mutate) {}

    /** @return array{importation: Trip, trip: Trip} */
    public function execute(User $user): array
    {
        return DB::transaction(function () use ($user) {
            // A repeat run must never replace, merge with, or delete business data.
            if (User::count() !== 1 || Company::exists() || Trip::withTrashed()->exists()
                || Product::exists() || Expense::exists()) {
                throw ValidationException::withMessages(['demo' => 'La demostración requiere una instalación sin empresas ni importaciones y con una sola cuenta. No se modificó ningún dato. Usa una instalación separada.']);
            }

            $company = Company::create(['user_id' => $user->id, 'nombre' => 'Empresa Ficticia · Demo',
                'rut' => null, 'descripcion' => 'Datos inventados para aprender a usar CostoReal.',
                'color' => '#315f56', 'icono' => 'globe-americas', 'activa' => true]);

            $importation = $this->load($user, ['company_id' => $company->id,
                'nombre' => 'Accesorios tecnológicos · Demo ficticia',
                'descripcion' => 'Productos, precios, gastos y tasa inventados. No representa cotizaciones, pagos ni impuestos reales.',
                'fecha_inicio' => '2026-01-15', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900'], [
                    ['Hub USB-C 7 en 1 · Demo', 20, '35', '6', '0.03', '58000'],
                    ['SSD portátil 500 GB · Demo', 30, '25', '3', '0.02', '39000'],
                    ['Cable USB-C · Demo', 50, '4', '2', '0.01', '8000'],
                ], [
                    ['Flete internacional · Demo', 'Transporte', 'USD', '200', 'weight', '2026-01-15'],
                    ['Seguro de carga · Demo', 'Otros Gastos', 'USD', '30', 'value', null],
                    ['Gestión aduanera · Demo', 'Otros Gastos', 'CLP', '90000', 'value', null],
                    ['Transporte local · Demo', 'Transporte', 'CLP', '45000', 'units', null],
                    ['Comisión de pago · Demo', 'Otros Gastos', 'CLP', '18000', 'value', null],
                    ['Almacenaje · Demo', 'Otros Gastos', 'CLP', '25000', 'volume', null],
                ]);

            // Every trip expense is shared by purchase value: the most expensive product carries more.
            $trip = $this->load($user, ['company_id' => $company->id,
                'nombre' => 'Viaje de compras a Brasil · Demo ficticia',
                'descripcion' => 'Viaje inventado: productos de belleza y ropa para vender, con pasaje, hotel, comida y traslados. Tasa y montos ficticios.',
                'fecha_inicio' => '2026-03-10', 'fecha_fin' => '2026-03-17', 'moneda_trabajo' => 'BRL', 'tipo_cambio' => '180'], [
                    ['Shampoo profesional · Demo', 60, '35', null, null, '12990'],
                    ['Crema para peinar · Demo', 40, '50', null, null, '16990'],
                    ['Vestido de verano · Demo', 30, '120', null, null, '39990'],
                ], [
                    ['Pasaje aéreo · Demo', 'Pasajes Aéreos', 'CLP', '380000', 'value', null],
                    ['Hotel · Demo', 'Alojamiento', 'BRL', '1000', 'value', null],
                    ['Comida · Demo', 'Alimentación', 'BRL', '400', 'value', null],
                    ['Traslados y Uber · Demo', 'Taxi/Uber', 'BRL', '250', 'value', null],
                ]);

            return ['importation' => $importation, 'trip' => $trip];
        });
    }

    /** Products: [name, quantity, unit cost, weight, volume, price]. Expenses: [name, base category, currency, amount, rule, paid date]. */
    private function load(User $user, array $context, array $products, array $expenses): Trip
    {
        $trip = $this->create->execute($user, $context);
        $rate = $context['tipo_cambio'];
        $this->mutate->execute($trip, function (Trip $current) use ($products, $expenses, $rate) {
            foreach ($products as [$name, $quantity, $cost, $weight, $volume, $price]) {
                $current->products()->create(['nombre' => $name, 'cantidad' => $quantity, 'moneda' => $current->moneda_trabajo,
                    'costo_unitario' => $cost, 'weight' => $weight, 'volume' => $volume,
                    'rate_mode' => 'estimated', 'rate_source' => self::SOURCE,
                    'precio_venta' => $price, 'subtotal_clp' => '0',
                    'costo_final_total_clp' => '0', 'costo_final_unitario_clp' => '0']);
            }
            foreach ($expenses as [$name, $category, $currency, $amount, $method, $paidOn]) {
                // Base categories exist after db:seed; without them the expense stays uncategorized.
                $current->expenses()->create(['expense_category_id' => ExpenseCategory::whereNull('user_id')->where('nombre', $category)->value('id'),
                    'concepto' => $name, 'moneda' => $currency, 'monto' => $amount, 'monto_clp' => '0',
                    'allocation_method' => $method, 'rate_mode' => $paidOn ? 'paid' : 'estimated',
                    'applied_rate' => $paidOn ? $rate : null, 'rate_source' => self::SOURCE, 'rate_date' => $paidOn]);
            }
        }, 'products_import', expectedRevision: $trip->revision, context: AccessContext::forActor($user->id));

        return $trip->fresh();
    }
}
