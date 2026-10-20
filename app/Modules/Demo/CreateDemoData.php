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

final class CreateDemoData
{
    public function __construct(private CreateImportation $create, private MutateTrip $mutate) {}

    public function execute(User $user): Trip
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
            $category = ExpenseCategory::create(['user_id' => $user->id, 'nombre' => 'Gastos ficticios · Demo',
                'icono' => 'truck', 'color' => '#315f56']);
            $trip = $this->create->execute($user, ['company_id' => $company->id,
                'nombre' => 'Accesorios tecnológicos · Demo ficticia',
                'descripcion' => 'Productos, precios, gastos y tasa inventados. No representa cotizaciones, pagos ni impuestos reales.',
                'fecha_inicio' => '2026-01-15', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900']);

            $this->mutate->execute($trip, function (Trip $current) use ($category) {
                foreach ([
                    ['Hub USB-C 7 en 1 · Demo', 20, '35', '6', '0.03', '58000'],
                    ['SSD portátil 500 GB · Demo', 30, '25', '3', '0.02', '39000'],
                    ['Cable USB-C · Demo', 50, '4', '2', '0.01', '8000'],
                ] as [$name, $quantity, $cost, $weight, $volume, $price]) {
                    $current->products()->create(['nombre' => $name, 'cantidad' => $quantity, 'moneda' => 'USD',
                        'costo_unitario' => $cost, 'weight' => $weight, 'volume' => $volume,
                        'rate_mode' => 'estimated', 'rate_source' => 'Referencia ficticia de demostración',
                        'precio_venta' => $price, 'subtotal_clp' => '0',
                        'costo_final_total_clp' => '0', 'costo_final_unitario_clp' => '0']);
                }

                foreach ([
                    ['Flete internacional · Demo', 'USD', '200', 'weight', 'paid'],
                    ['Seguro de carga · Demo', 'USD', '30', 'value', 'estimated'],
                    ['Gestión aduanera · Demo', 'CLP', '90000', 'value', 'estimated'],
                    ['Transporte local · Demo', 'CLP', '45000', 'units', 'estimated'],
                    ['Comisión de pago · Demo', 'CLP', '18000', 'value', 'estimated'],
                    ['Almacenaje · Demo', 'CLP', '25000', 'volume', 'estimated'],
                ] as [$name, $currency, $amount, $method, $mode]) {
                    $current->expenses()->create(['expense_category_id' => $category->id, 'concepto' => $name,
                        'moneda' => $currency, 'monto' => $amount, 'monto_clp' => '0',
                        'allocation_method' => $method, 'rate_mode' => $mode,
                        'applied_rate' => $mode === 'paid' ? '900' : null,
                        'rate_source' => 'Referencia ficticia de demostración',
                        'rate_date' => $mode === 'paid' ? '2026-01-15' : null]);
                }
            }, 'products_import', expectedRevision: $trip->revision, context: AccessContext::forActor($user->id));

            return $trip->fresh();
        });
    }
}
