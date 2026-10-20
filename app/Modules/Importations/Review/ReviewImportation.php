<?php

namespace App\Modules\Importations\Review;

use App\Models\Trip;
use App\Modules\Costing\Domain\Currency;
use App\Modules\Costing\Domain\Rate;
use App\Modules\Costing\Reconciliation\CompareStoredCosts;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReviewImportation
{
    public function __construct(private CompareStoredCosts $compare) {}

    public function execute(Trip $trip): array
    {
        $data = $this->compare->execute($trip)['comparison'];
        $blocks = $data['errors'];
        $warnings = [];
        try {
            new Rate(Currency::supported($trip->moneda_trabajo), (string) $trip->tipo_cambio);
        } catch (\DomainException $e) {
            $blocks[] = 'Revisa la tasa de referencia: '.$e->getMessage();
        }
        if (! $trip->products->count()) {
            $blocks[] = 'Agrega al menos un producto.';
        }
        if ($data['after'] && $data['after']['unassigned'] !== '0') {
            $blocks[] = 'Distribuye todos los gastos antes de cerrar.';
        }
        if ($data['differences']) {
            $blocks[] = 'Los costos guardados difieren del motor actual. Compara y concilia las diferencias en Historial antes de cerrar.';
        }
        $rows = $trip->products->concat($trip->expenses);
        // Operations in CLP never use the reference rate, whatever their conversion mode says.
        $estimated = $rows->where('rate_mode', 'estimated')->where('moneda', '!==', 'CLP')->count();
        $legacy = $rows->where('rate_source', 'legacy-unverified')->count();
        $missingPaid = $rows->filter(fn ($row) => $row->rate_mode === 'paid' && (! $row->applied_rate || ! $row->rate_source || $row->rate_source === 'legacy-unverified' || ! $row->rate_date))->count();
        if ($missingPaid) {
            $blocks[] = 'Hay pagos sin tasa, fuente histórica identificada o fecha. Completa su evidencia histórica.';
        }
        foreach ($rows as $row) {
            if (! in_array($row->rate_mode, ['estimated', 'paid'], true)) {
                $blocks[] = 'Hay operaciones sin un tipo de conversión válido.';
            }
            if ($row->rate_mode === 'paid') {
                if (Validator::make(['date' => $row->rate_date], ['date' => 'required|date_format:Y-m-d'])->fails()) {
                    $blocks[] = 'Hay pagos sin una fecha histórica válida.';
                }
                try {
                    new Rate(Currency::supported($row->moneda), (string) $row->applied_rate);
                } catch (\DomainException $e) {
                    $blocks[] = $e->getMessage();
                }
            }
        }
        $priced = $trip->products->filter(function ($row) {
            try {
                return $row->precio_venta !== null && BigDecimal::of((string) $row->precio_venta)->isGreaterThan(0);
            } catch (MathException $e) {
                return false;
            }
        })->count();
        if ($priced < $trip->products->count()) {
            $warnings[] = 'Hay precios pendientes. La proyección del lote está incompleta.';
        }
        if ($estimated) {
            $warnings[] = $estimated === 1 ? 'Hay una operación estimada que usa la tasa de referencia.' : "Hay {$estimated} operaciones estimadas que usan la tasa de referencia.";
        }
        if ($legacy) {
            $warnings[] = $legacy === 1 ? 'Hay una operación con procedencia histórica de tasa sin verificar.' : "Hay {$legacy} operaciones con procedencia histórica de tasa sin verificar.";
        }
        $trip->loadMissing('documents');
        $documentIds = $trip->documents->pluck('id');
        if ($trip->expenses->contains(fn ($expense) => ! $expense->document_id || ! $documentIds->contains($expense->document_id))) {
            $warnings[] = 'Hay gastos sin comprobante adjunto. Los comprobantes son opcionales según la política actual.';
        }

        return ['revision' => $trip->revision, 'state' => $trip->estado, 'can_close' => $trip->estado === 'activo' && ! $blocks,
            'blocks' => array_values(array_unique($blocks)), 'warnings' => $warnings,
            'stored_total' => $data['before']['total'] ?? null, 'calculated_total' => $data['after']['total'] ?? null,
            'differences_count' => count($data['differences']), 'document_policy' => 'optional'];
    }

    public function assertReady(Trip $trip): void
    {
        $review = $this->execute($trip);
        if ($review['blocks']) {
            throw ValidationException::withMessages(['cierre' => $review['blocks']]);
        }
    }
}
