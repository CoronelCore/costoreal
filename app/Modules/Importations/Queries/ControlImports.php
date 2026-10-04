<?php

namespace App\Modules\Importations\Queries;

use App\Modules\Access\AccessContext;
use App\Modules\Importations\Data\ImportationData;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

final class ControlImports
{
    public function read(AccessContext $context, array $filters): array
    {
        $base = $context->trips();
        if (isset($filters['q']) && $filters['q'] !== '') {
            $base->where('nombre', 'like', '%'.$filters['q'].'%');
        }
        if (isset($filters['state']) && $filters['state'] !== '') {
            $base->where('estado', $filters['state']);
        }
        if (isset($filters['from']) && $filters['from'] !== '') {
            $base->whereDate('fecha_inicio', '>=', $filters['from']);
        }
        if (isset($filters['to']) && $filters['to'] !== '') {
            $base->whereDate('fecha_inicio', '<=', $filters['to']);
        }
        $financial = (clone $base)->withFinancialTotals()->withCount(['products', 'expenses',
            'products as priced_count' => fn ($q) => $q->whereNotNull('precio_venta')->where('precio_venta', '>', 0)]);
        $aggregate = DB::query()->fromSub((clone $financial)->toBase(), 'filtered')->selectRaw(
            "COUNT(*) as count, COALESCE(SUM(total_productos_clp),0) as purchase, COALESCE(SUM(total_gastos_clp),0) as expenses,
            COALESCE(SUM(CASE WHEN estado='activo' THEN 1 ELSE 0 END),0) as active,
            COALESCE(SUM(products_count),0) as products, COALESCE(SUM(priced_count),0) as priced")->first();
        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'cost' => $financial->orderByDesc('costo_calculado'),'name' => $financial->orderBy('nombre'),default => $financial->latest()
        };
        $trips = $financial->orderBy('id', 'desc')->with($context->companyRelation())->paginate(20, ['*'], 'page', $filters['page'] ?? 1)->appends(array_diff_key($filters, ['page' => true]))->through(fn ($trip) => ImportationData::listing($trip));

        return ['trips' => $trips, 'filters' => (object) array_diff_key($filters, ['page' => true]),
            'metrics' => ['count' => (int) $aggregate->count, 'active' => (int) $aggregate->active, 'purchase' => (string) $aggregate->purchase,
                'expenses' => (string) $aggregate->expenses, 'total' => (string) BigDecimal::of((string) $aggregate->purchase)->plus((string) $aggregate->expenses),
                'products' => (int) $aggregate->products, 'priced' => (int) $aggregate->priced]];
    }
}
