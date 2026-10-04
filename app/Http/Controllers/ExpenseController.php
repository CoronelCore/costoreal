<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Documents\StoreDocument;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private MutateTrip $mutation, private StoreDocument $storage) {}

    public function store(ExpenseRequest $request, Trip $trip)
    {
        $data = $request->safe()->except(['expected_revision', 'comprobante', 'eliminar_comprobante']);
        $context = AccessContext::forRequest($request);
        $stage = $request->hasFile('comprobante') ? $this->storage->stage($request->file('comprobante'), $context) : null;
        try {
            $this->mutation->execute($trip, function ($current) use ($data, $stage, $context) {
                $expense = $current->expenses()->create([...$data, 'monto_clp' => 0]);
                if ($stage) {
                    $document = $this->storage->attach($stage, $current, 'receipt', $expense, $context);
                    $expense->update(['document_id' => $document->id, 'comprobante' => $stage['storage_path']]);
                }
            }, 'expense_create', context: $context);
        } catch (\Throwable $e) {
            $this->storage->discard($stage, $context);
            throw $e;
        }

        return $request->expectsJson() ? response()->json(['revision' => $trip->fresh()->revision]) : to_route('trips.show', $trip)->with('success', 'Gasto agregado');
    }

    public function update(ExpenseRequest $request, Trip $trip, Expense $expense)
    {
        $data = $request->safe()->except(['expected_revision', 'comprobante', 'eliminar_comprobante']);
        $context = AccessContext::forRequest($request);
        $stage = $request->hasFile('comprobante') ? $this->storage->stage($request->file('comprobante'), $context) : null;
        try {
            $this->mutation->execute($trip, function ($current) use ($request, $data, $stage, $expense, $context) {
                $record = $current->expenses()->findOrFail($expense->id);
                $record->update($data);
                if ($stage) {
                    $document = $this->storage->attach($stage, $current, 'receipt', $record, $context);
                    $record->update(['document_id' => $document->id, 'comprobante' => $stage['storage_path']]);
                } elseif ($request->boolean('eliminar_comprobante')) {
                    $record->update(['document_id' => null, 'comprobante' => null]);
                }
            }, 'expense_update', context: $context);
        } catch (\Throwable $e) {
            $this->storage->discard($stage, $context);
            throw $e;
        }

        return $request->expectsJson() ? response()->json(['revision' => $trip->fresh()->revision]) : to_route('trips.show', $trip)->with('success', 'Gasto actualizado');
    }

    public function destroy(Request $request, Trip $trip, Expense $expense)
    {
        $this->authorize('update', $trip);
        $this->mutation->execute($trip, fn ($current) => $current->expenses()->findOrFail($expense->id)->delete(), 'expense_delete');

        // Attachments remain recoverable in the trip's document library.
        return $request->expectsJson() ? response()->json(['revision' => $trip->fresh()->revision]) : to_route('trips.show', $trip)->with('success', 'Gasto eliminado; sus documentos se conservan');
    }
}
