<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Product;

class ProrrateoService
{
    /**
     * Calcula el prorrateo de gastos para todos los productos de un viaje
     */
    public function calcularProrrateo(Trip $trip): void
    {
        $totalGastosCLP = $trip->expenses()->sum('monto_clp');
        $products = $trip->products;

        if ($products->isEmpty()) {
            return;
        }

        // Calcular subtotales de productos en CLP
        $totalProductosCLP = 0;
        foreach ($products as $product) {
            $tc = $this->getTipoCambio($product->moneda, $trip);
            $subtotalCLP = $product->costo_unitario * $product->cantidad * $tc;
            $product->subtotal_clp = $subtotalCLP;
            $totalProductosCLP += $subtotalCLP;
        }

        // Calcular prorrateo para cada producto
        foreach ($products as $product) {
            $porcentaje = $totalProductosCLP > 0 ? $product->subtotal_clp / $totalProductosCLP : 0;
            $gastosProrrateadosCLP = $totalGastosCLP * $porcentaje;
            $costoFinalTotalCLP = $product->subtotal_clp + $gastosProrrateadosCLP;
            $costoFinalUnitarioCLP = $costoFinalTotalCLP / $product->cantidad;

            $product->gastos_prorrateados_clp = $gastosProrrateadosCLP;
            $product->costo_final_unitario_clp = $costoFinalUnitarioCLP;
            $product->costo_final_total_clp = $costoFinalTotalCLP;
            $product->save();
        }
    }

    /**
     * Convierte un monto a CLP según la moneda
     */
    public function convertirACLP(float $monto, string $moneda, Trip $trip): float
    {
        $tc = $this->getTipoCambio($moneda, $trip);
        return $monto * $tc;
    }

    /**
     * Obtiene el tipo de cambio para una moneda específica
     */
    private function getTipoCambio(string $moneda, Trip $trip): float
    {
        if ($moneda === 'CLP') {
            return 1;
        }

        if ($moneda === $trip->moneda_trabajo) {
            return $trip->tipo_cambio;
        }

        return 1;
    }

    /**
     * Calcula estadísticas del viaje
     */
    public function obtenerEstadisticas(Trip $trip): array
    {
        $totalGastosCLP = $trip->expenses()->sum('monto_clp');
        $totalProductosCLP = $trip->products()->sum('subtotal_clp');
        $totalGastosProrrateadosCLP = $trip->products()->sum('gastos_prorrateados_clp');
        $costoTotalFinalCLP = $trip->products()->sum('costo_final_total_clp');

        return [
            // Estadísticas de gastos
            'total_gastos_clp' => $totalGastosCLP,

            // Estadísticas de productos
            'total_productos_clp' => $totalProductosCLP,
            'total_gastos_prorrateados_clp' => $totalGastosProrrateadosCLP,
            'costo_total_final_clp' => $costoTotalFinalCLP,

            // Costo total (para compatibilidad)
            'costo_total' => $totalGastosCLP + $totalProductosCLP,
        ];
    }

    /**
     * Obtiene estadísticas por categoría de gastos
     */
    public function obtenerGastosPorCategoria(Trip $trip): array
    {
        return $trip->expenses()
            ->selectRaw('expense_category_id, SUM(monto_clp) as total')
            ->groupBy('expense_category_id')
            ->with('category')
            ->get()
            ->mapWithKeys(function ($expense) {
                $categoryName = $expense->category ? $expense->category->nombre : 'Sin categoría';
                return [$categoryName => $expense->total];
            })
            ->toArray();
    }
}
