<?php

namespace App\Modules\Importations\Data;

use App\Models\Product;
use App\Modules\Reporting\ProductResult;

final class ProductData
{
    public static function editable(Product $product): array
    {
        return [
            'id' => (int) $product->id, 'nombre' => $product->nombre,
            'moneda' => $product->moneda, 'cantidad' => (int) $product->cantidad,
            'costo_unitario' => (string) $product->costo_unitario,
            'weight' => $product->weight === null ? null : (string) $product->weight,
            'volume' => $product->volume === null ? null : (string) $product->volume,
            'rate_mode' => $product->rate_mode,
            'applied_rate' => $product->applied_rate === null ? null : (string) $product->applied_rate,
            'rate_source' => $product->rate_source, 'rate_date' => $product->rate_date,
        ];
    }

    public static function fromModel(Product $product): array
    {
        return [...self::editable($product),
            'subtotal_clp' => (string) $product->subtotal_clp,
            'gastos_prorrateados_clp' => (string) $product->gastos_prorrateados_clp,
            'costo_final_total_clp' => (string) $product->costo_final_total_clp,
            'costo_final_unitario_clp' => (string) $product->costo_final_unitario_clp,
            'precio_venta' => $product->precio_venta === null ? null : (string) $product->precio_venta,
            'document_id' => $product->document_id === null ? null : (int) $product->document_id,
            'resultado' => ProductResult::of($product),
        ];
    }
}
