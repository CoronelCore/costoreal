<?php

namespace App\Modules\Audit;

final class CompareAudit
{
    private const TRIP = ['nombre', 'descripcion', 'company_id', 'estado', 'fecha_inicio', 'fecha_fin', 'moneda_trabajo', 'tipo_cambio', 'costo_total', 'deleted_at'];

    private const PRODUCT = ['nombre', 'cantidad', 'moneda', 'costo_unitario', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'weight', 'volume', 'subtotal_clp', 'gastos_prorrateados_clp', 'costo_final_total_clp', 'costo_final_unitario_clp', 'precio_venta', 'document_id'];

    private const EXPENSE = ['concepto', 'expense_category_id', 'moneda', 'monto', 'monto_clp', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id', 'document_id'];

    private const DOCUMENT = ['filename', 'mime', 'size', 'kind', 'deleted_at'];

    public function build(array $before, array $after): array
    {
        $rows = [];
        $fields = $this->fields($before, $after, self::TRIP);
        if ($fields) {
            $rows[] = ['kind' => 'trip', 'id' => $after['id'] ?? $before['id'] ?? null, 'name' => 'Configuración de la importación', 'change' => 'updated', 'fields' => $fields];
        }
        foreach (['products' => self::PRODUCT, 'expenses' => self::EXPENSE, 'documents' => self::DOCUMENT] as $kind => $allowed) {
            $old = collect($before[$kind] ?? [])->keyBy('id');
            $new = collect($after[$kind] ?? [])->keyBy('id');
            foreach ($old->keys()->merge($new->keys())->unique()->sort() as $id) {
                $left = $old->get($id);
                $right = $new->get($id);
                $fields = $this->fields($left ?? [], $right ?? [], $allowed);
                if (! $fields && $left !== null && $right !== null) {
                    continue;
                }
                $row = $right ?? $left;
                $rows[] = ['kind' => $kind, 'id' => $id, 'name' => $row['nombre'] ?? $row['concepto'] ?? $row['filename'] ?? "Registro {$id}",
                    'change' => $left === null ? 'added' : ($right === null ? 'removed' : 'updated'), 'fields' => $fields];
            }
        }

        return $rows;
    }

    private function fields(array $before, array $after, array $allowed): array
    {
        $changes = [];
        foreach ($allowed as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($old !== $new) {
                $changes[] = ['field' => $field, 'before' => $old, 'after' => $new];
            }
        }

        return $changes;
    }
}
