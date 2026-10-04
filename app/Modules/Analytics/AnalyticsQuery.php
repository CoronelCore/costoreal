<?php

namespace App\Modules\Analytics;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Modules\Access\AccessContext;
use App\Modules\Costing\Domain\DecimalSum;
use App\Modules\Pricing\Domain\ProjectionAccumulator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exact read model: one cohort, no SQL monetary SUM or float arithmetic and no related model serialization. */
final class AnalyticsQuery
{
    public function read(AccessContext $context, array $filters, Request $request): array
    {
        return DB::transaction(fn () => $this->snapshot($context, $filters, $request));
    }

    private function snapshot(AccessContext $context, array $filters, Request $request): array
    {
        $base = $context->trips();
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $base->whereDate('fecha_inicio', $operator, $filters[$key]);
            }
        }
        if (! empty($filters['state'])) {
            $base->where('estado', $filters['state']);
        }
        $ids = (clone $base)->select('trips.id');
        $trips = [];
        $revisions = [];
        foreach ((clone $base)->orderBy('id')->get(['id', 'nombre', 'fecha_inicio', 'estado', 'revision']) as $trip) {
            $revisions[$trip->id] = $trip->revision;
            $trips[$trip->id] = ['id' => $trip->id, 'name' => $trip->nombre, 'date' => $trip->fecha_inicio?->format('Y-m-d'), 'state' => $trip->estado,
                'purchase' => new DecimalSum, 'expenses' => new DecimalSum, 'assigned' => new DecimalSum, 'projection' => new ProjectionAccumulator];
        }
        $overall = new ProjectionAccumulator;
        foreach (Product::whereIn('trip_id', (clone $ids))->select(['trip_id', 'cantidad', 'subtotal_clp', 'gastos_prorrateados_clp', 'costo_final_total_clp', 'precio_venta'])->toBase()->cursor() as $product) {
            foreach (['subtotal_clp', 'gastos_prorrateados_clp', 'costo_final_total_clp', 'precio_venta'] as $field) {
                if ($product->{$field} !== null) {
                    $product->{$field} = DecimalSum::money($product->{$field});
                }
            }
            if (! isset($trips[$product->trip_id])) {
                $this->changed();
            }
            $product->cantidad = (int) $product->cantidad;
            $row = &$trips[$product->trip_id];
            $row['purchase']->add($product->subtotal_clp);
            $row['assigned']->add($product->gastos_prorrateados_clp);
            $row['projection']->add($product);
            unset($row);
        }
        // Only shared or owned category metadata may cross the read boundary.
        $visible = ExpenseCategory::visibleTo($context->user->id)->get(['id', 'nombre'])->keyBy('id');
        $categories = [];
        $latest = [];
        foreach (Expense::whereIn('trip_id', (clone $ids))->select(['id', 'trip_id', 'expense_category_id', 'concepto', 'monto_clp', 'created_at'])->orderByDesc('created_at')->orderByDesc('id')->toBase()->cursor() as $expense) {
            if (! isset($trips[$expense->trip_id])) {
                $this->changed();
            }
            $expense->monto_clp = DecimalSum::money($expense->monto_clp);
            $row = &$trips[$expense->trip_id];
            $row['expenses']->add($expense->monto_clp);
            $category = $visible->get($expense->expense_category_id);
            $key = $category?->id ?? 0;
            $categories[$key] ??= ['id' => $key, 'name' => $category?->nombre ?? 'Sin categoría', 'count' => 0, 'total' => new DecimalSum];
            $categories[$key]['count']++;
            $categories[$key]['total']->add($expense->monto_clp);
            if (count($latest) < 10) {
                $latest[] = ['id' => (int) $expense->id, 'trip_id' => (int) $expense->trip_id, 'trip_name' => $row['name'], 'name' => $expense->concepto,
                    'category' => $categories[$key]['name'], 'date' => $expense->created_at ? substr($expense->created_at, 0, 10) : null, 'amount' => (string) $expense->monto_clp];
            }
            unset($row);
        }
        // READ COMMITTED engines must not return a mixture of financial revisions.
        if ($revisions !== (clone $base)->orderBy('id')->pluck('revision', 'id')->all()) {
            $this->changed();
        }
        $purchase = BigDecimal::zero();
        $expenses = BigDecimal::zero();
        $unassigned = BigDecimal::zero();
        $months = [];
        $active = 0;
        foreach ($trips as &$trip) {
            foreach (['purchase', 'expenses', 'assigned'] as $field) {
                $trip[$field] = $trip[$field]->value();
            }
            $purchase = $purchase->plus($trip['purchase']);
            $expenses = $expenses->plus($trip['expenses']);
            $missing = $trip['expenses']->minus($trip['assigned']);
            $unassigned = $unassigned->plus($missing);
            $overall->merge($trip['projection']);
            $trip['projection'] = $trip['projection']->summary((string) $missing);
            $trip['unassigned'] = (string) $missing;
            $trip['total'] = (string) $trip['purchase']->plus($trip['expenses']);
            $trip['purchase'] = (string) $trip['purchase'];
            $trip['expenses'] = (string) $trip['expenses'];
            unset($trip['assigned']);
            $active += $trip['state'] === 'activo' ? 1 : 0;
            $month = $trip['date'] ? substr($trip['date'], 0, 7) : 'undated';
            $months[$month] ??= ['month' => $month, 'count' => 0, 'purchase' => BigDecimal::zero(), 'expenses' => BigDecimal::zero(), 'total' => BigDecimal::zero()];
            $months[$month]['count']++;
            foreach (['purchase', 'expenses', 'total'] as $field) {
                $months[$month][$field] = $months[$month][$field]->plus($trip[$field]);
            }
        }
        unset($trip);
        $total = $purchase->plus($expenses);
        $count = count($trips);
        usort($trips, fn ($a, $b) => BigDecimal::of($b['total'])->compareTo($a['total']) ?: $a['id'] <=> $b['id']);
        usort($categories, fn ($a, $b) => $b['total']->value()->compareTo($a['total']->value()) ?: $a['id'] <=> $b['id']);
        foreach ($categories as &$category) {
            $category['total'] = (string) $category['total']->value();
        }
        unset($category);
        krsort($months);
        // Undated imports always appear after dated months, never disappear silently.
        if (isset($months['undated'])) {
            $undated = $months['undated'];
            unset($months['undated']);
            $months['undated'] = $undated;
        }
        foreach ($months as &$month) {
            foreach (['purchase', 'expenses', 'total'] as $field) {
                $month[$field] = (string) $month[$field];
            }
        }
        unset($month);

        return ['metrics' => ['count' => $count, 'active' => $active, 'purchase' => (string) $purchase, 'expenses' => (string) $expenses,
            'total' => (string) $total, 'average' => (string) ($count ? $total->dividedBy($count, 2, RoundingMode::HALF_UP) : BigDecimal::zero()), 'unassigned' => (string) $unassigned],
            'projection' => $overall->summary((string) $unassigned),
            'imports' => $this->page(array_values($trips), 10, 'imports_page', $request, $filters),
            'categories' => $this->page(array_values($categories), 10, 'categories_page', $request, $filters),
            'months' => $this->page(array_values($months), 12, 'months_page', $request, $filters),
            'latestExpenses' => $latest,
            'filters' => (object) array_intersect_key($filters, array_flip(['from', 'to', 'state']))];
    }

    private function changed(): never
    {
        throw ValidationException::withMessages(['analytics' => 'Las importaciones cambiaron durante la lectura. Vuelve a aplicar los filtros.']);
    }

    private function page(array $rows, int $size, string $name, Request $request, array $filters): array
    {
        $page = min((int) ($filters[$name] ?? 1), max(1, (int) ceil(count($rows) / $size)));
        $paginator = new LengthAwarePaginator(array_slice($rows, ($page - 1) * $size, $size), count($rows), $size, $page,
            ['path' => $request->url(), 'pageName' => $name]);
        $paginator->appends(array_filter($filters, fn ($value, $key) => $key !== $name && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH));

        return $paginator->toArray();
    }
}
