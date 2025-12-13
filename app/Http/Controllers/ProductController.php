<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Trip;
use App\Services\ProrrateoService;
use Illuminate\Http\Request;

class ProductController extends Controller
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
            'nombre' => 'required|string|max:255',
            'moneda' => 'required|in:CLP,' . $trip->moneda_trabajo,
            'cantidad' => 'required|integer|min:1',
            'costo_unitario' => 'required|numeric|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $validated['trip_id'] = $trip->id;

        // Calcular subtotal en CLP
        $tc = $validated['moneda'] === 'CLP' ? 1 : $trip->tipo_cambio;
        $validated['subtotal_clp'] = $validated['costo_unitario'] * $validated['cantidad'] * $tc;
        $validated['costo_final_unitario_clp'] = 0;
        $validated['costo_final_total_clp'] = 0;

        // Manejar upload de imagen
        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        Product::create($validated);
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)->with('success', 'Producto agregado exitosamente');
    }

    public function update(Request $request, Trip $trip, Product $product)
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'moneda' => 'required|in:CLP,' . $trip->moneda_trabajo,
            'cantidad' => 'required|integer|min:1',
            'costo_unitario' => 'required|numeric|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'eliminar_imagen' => 'nullable|boolean',
        ]);

        // Calcular subtotal en CLP
        $tc = $validated['moneda'] === 'CLP' ? 1 : $trip->tipo_cambio;
        $validated['subtotal_clp'] = $validated['costo_unitario'] * $validated['cantidad'] * $tc;

        // Manejar upload de nueva imagen
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior si existe
            if ($product->imagen) {
                \Storage::disk('public')->delete($product->imagen);
            }
            $validated['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        // Manejar eliminación de imagen
        if ($request->boolean('eliminar_imagen') && $product->imagen) {
            \Storage::disk('public')->delete($product->imagen);
            $validated['imagen'] = null;
        }

        $product->update($validated);
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)->with('success', 'Producto actualizado exitosamente');
    }

    public function updatePrecioVenta(Request $request, Trip $trip, Product $product)
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'precio_venta' => 'nullable|numeric|min:0',
        ]);

        $product->update($validated);

        return redirect()->route('trips.show', $trip)->with('success', 'Precio de venta actualizado');
    }

    public function destroy(Trip $trip, Product $product)
    {
        $this->authorize('update', $trip);

        // Eliminar imagen si existe
        if ($product->imagen) {
            \Storage::disk('public')->delete($product->imagen);
        }

        $product->delete();
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)->with('success', 'Producto eliminado exitosamente');
    }
}
