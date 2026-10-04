<?php

namespace App\Modules\Importations\Data;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Trip;
use App\Modules\Reporting\FinancialSummaryData;

/** Explicit public read contracts; no owner IDs, storage paths or implicit accessors. */
final class ImportationData
{
    public static function configuration(Trip $trip): array
    {
        return ['id' => (int) $trip->id, 'revision' => (int) $trip->revision,
            'nombre' => $trip->nombre, 'descripcion' => $trip->descripcion,
            'fecha_inicio' => $trip->fecha_inicio?->format('Y-m-d'), 'fecha_fin' => $trip->fecha_fin?->format('Y-m-d'),
            'moneda_base' => $trip->moneda_base, 'moneda_trabajo' => $trip->moneda_trabajo,
            'tipo_cambio' => (string) $trip->tipo_cambio, 'estado' => $trip->estado,
            'company_id' => $trip->relationLoaded('company') && $trip->company === null ? null : ($trip->company_id === null ? null : (int) $trip->company_id)];
    }

    public static function listing(Trip $trip): array
    {
        return [...self::configuration($trip), 'company' => self::company($trip),
            'total_productos_clp' => (string) ($trip->getAttribute('total_productos_clp') ?? '0'),
            'total_gastos_clp' => (string) ($trip->getAttribute('total_gastos_clp') ?? '0'),
            'costo_total' => (string) $trip->costo_total,
            'products_count' => (int) $trip->products_count, 'expenses_count' => (int) $trip->expenses_count,
            'priced_count' => (int) $trip->priced_count];
    }

    public static function detail(Trip $trip, FinancialSummaryData $summary): array
    {
        $duplicates = $trip->documents->countBy('sha256');

        return [...self::configuration($trip), 'company' => self::company($trip),
            'costo_total' => $summary->statistics['costo_total'],
            'products' => $trip->products->map(fn ($p) => ProductData::fromModel($p))->all(),
            'expenses' => $trip->expenses->map(fn ($e) => self::expense($e))->all(),
            'documents' => $trip->documents->map(fn ($d) => self::document($d, $duplicates[$d->sha256] ?? 1))->all()];
    }

    public static function document(Document $document, int $sameContentCount = 1): array
    {
        return ['id' => (int) $document->id, 'filename' => $document->filename,
            'mime' => $document->mime, 'size' => (int) $document->size,
            'kind' => $document->kind, 'same_content_count' => $sameContentCount, 'download_url' => $document->download_url];
    }

    private static function expense(Expense $expense): array
    {
        return ['id' => (int) $expense->id, 'concepto' => $expense->concepto,
            'expense_category_id' => $expense->category === null ? null : (int) $expense->category->id,
            'category' => $expense->category ? $expense->category->only(['id', 'nombre']) : null,
            'moneda' => $expense->moneda, 'monto' => (string) $expense->monto, 'monto_clp' => (string) $expense->monto_clp,
            'rate_mode' => $expense->rate_mode, 'applied_rate' => $expense->applied_rate === null ? null : (string) $expense->applied_rate,
            'rate_source' => $expense->rate_source, 'rate_date' => $expense->rate_date,
            'allocation_method' => $expense->allocation_method,
            'target_product_id' => $expense->target_product_id === null ? null : (int) $expense->target_product_id,
            'document_id' => $expense->document_id === null ? null : (int) $expense->document_id];
    }

    private static function company(Trip $trip): ?array
    {
        return $trip->company ? $trip->company->only(['id', 'nombre', 'color', 'icono']) : null;
    }
}
