<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Trip;
use App\Services\ProrrateoService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    protected $prorrateoService;

    public function __construct(ProrrateoService $prorrateoService)
    {
        $this->prorrateoService = $prorrateoService;
    }

    public function store(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'concepto' => 'required|string|max:255',
            'moneda' => 'required|in:CLP,' . $trip->moneda_trabajo,
            'monto' => 'required|numeric|min:0',
            'comprobante' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $validated['trip_id'] = $trip->id;
        $validated['monto_clp'] = $this->prorrateoService->convertirACLP(
            $validated['monto'],
            $validated['moneda'],
            $trip
        );

        // Manejar upload de comprobante
        if ($request->hasFile('comprobante')) {
            $validated['comprobante'] = $request->file('comprobante')->store('comprobantes', 'public');
        }

        Expense::create($validated);
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)->with('success', 'Gasto agregado exitosamente');
    }

    public function update(Request $request, Trip $trip, Expense $expense)
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'concepto' => 'required|string|max:255',
            'moneda' => 'required|in:CLP,' . $trip->moneda_trabajo,
            'monto' => 'required|numeric|min:0',
            'comprobante' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'eliminar_comprobante' => 'nullable|boolean',
        ]);

        $validated['monto_clp'] = $this->prorrateoService->convertirACLP(
            $validated['monto'],
            $validated['moneda'],
            $trip
        );

        // Manejar upload de nuevo comprobante
        if ($request->hasFile('comprobante')) {
            // Eliminar comprobante anterior si existe
            if ($expense->comprobante) {
                \Storage::disk('public')->delete($expense->comprobante);
            }
            $validated['comprobante'] = $request->file('comprobante')->store('comprobantes', 'public');
        }

        // Manejar eliminación de comprobante
        if ($request->boolean('eliminar_comprobante') && $expense->comprobante) {
            \Storage::disk('public')->delete($expense->comprobante);
            $validated['comprobante'] = null;
        }

        $expense->update($validated);
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)->with('success', 'Gasto actualizado exitosamente');
    }

    public function destroy(Trip $trip, Expense $expense)
    {
        $this->authorize('update', $trip);

        // Eliminar comprobante si existe
        if ($expense->comprobante) {
            \Storage::disk('public')->delete($expense->comprobante);
        }

        $expense->delete();
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)->with('success', 'Gasto eliminado exitosamente');
    }
}
