<?php

namespace App\Modules\Audit;

final class AuditSummary
{
    public function build(array $before, array $after): array
    {
        return ['version' => 1, 'before' => $this->snapshot($before), 'after' => $this->snapshot($after)];
    }

    private function snapshot(array $data): array
    {
        return ['state' => $data['estado'] ?? null, 'reference_rate' => $data['tipo_cambio'] ?? null,
            'cost' => isset($data['costo_total']) ? (string) $data['costo_total'] : null,
            'products' => count($data['products'] ?? []), 'expenses' => count($data['expenses'] ?? []),
            'documents' => count($data['documents'] ?? []), 'deleted_at' => $data['deleted_at'] ?? null];
    }
}
