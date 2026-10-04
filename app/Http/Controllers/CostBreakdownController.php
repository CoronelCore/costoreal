<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostBreakdownController extends Controller
{
    public function show(Request $request, Trip $trip, string $runId, string $productId)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);

        return DB::transaction(function () use ($trip, $runId, $productId) {
            $current = $trip->fresh();
            abort_if($current === null, 404);
            $this->authorize('view', $current);
            $run = $current->costRuns()->select('id', 'revision', 'reason', 'algorithm', 'created_at')->findOrFail($runId);
            $view = DB::table('cost_run_views')->where('cost_run_id', $run->id)->first();
            abort_if($view === null, 409, 'El detalle de esta revisión antigua aún está pendiente de preparar. Sus costos originales se conservan.');
            $line = DB::table('cost_run_lines')->where('cost_run_id', $run->id)->where('product_id', $productId)->first();
            abort_if($line === null, 404);
            $allocations = DB::table('cost_allocations as a')->leftJoin('cost_run_expenses as e', function ($join) {
                $join->on('e.cost_run_id', '=', 'a.cost_run_id')->on('e.expense_id', '=', 'a.expense_id');
            })->where('a.cost_run_id', $run->id)->where('a.product_id', $productId)->orderBy('a.expense_id')->orderBy('a.id')
                ->select('a.expense_id', 'a.product_id', 'a.amount', 'a.method', 'a.trace', 'e.name')->paginate(20)
                ->through(fn ($row) => ['expense_id' => (int) $row->expense_id, 'product_id' => (int) $row->product_id,
                    'name' => $row->name ?? "Gasto {$row->expense_id}", 'amount' => (string) $row->amount, 'method' => $row->method,
                    'trace' => $row->trace === null ? null : json_decode($row->trace, true, flags: JSON_THROW_ON_ERROR)]);

            return response()->json(['current_revision' => $current->revision,
                'run' => ['id' => (int) $run->id, 'revision' => (int) $run->revision, 'reason' => $run->reason,
                    'algorithm' => $run->algorithm, 'created_at' => $run->created_at->toISOString()],
                'line' => json_decode($line->data, true, flags: JSON_THROW_ON_ERROR),
                'totals' => json_decode($view->summary, true, flags: JSON_THROW_ON_ERROR), 'allocations' => $allocations]);
        });
    }
}
