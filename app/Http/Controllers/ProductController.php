<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Documents\StoreDocument;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Importations\Data\ProductData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private MutateTrip $mutation, private StoreDocument $storage) {}

    public function show(Trip $trip, Product $product)
    {
        $this->authorize('view', $trip);

        return DB::transaction(function () use ($trip, $product) {
            $current = $trip->fresh();
            $record = $current->products()->findOrFail($product->id);

            return response()->json(['revision' => $current->revision, 'editable' => $current->estado === 'activo',
                'product' => ProductData::editable($record)]);
        });
    }

    public function store(ProductRequest $request, Trip $trip)
    {
        $data = $request->safe()->except(['expected_revision', 'imagen', 'eliminar_imagen']);
        $stage = $request->hasFile('imagen') ? $this->storage->stage($request->file('imagen')) : null;
        try {
            $this->mutation->execute($trip, function ($current) use ($data, $stage) {
                $product = $current->products()->create([...$data, 'subtotal_clp' => 0, 'costo_final_unitario_clp' => 0, 'costo_final_total_clp' => 0]);
                if ($stage) {
                    $document = $this->storage->attach($stage, $current, 'product_image', $product);
                    $product->update(['document_id' => $document->id, 'imagen' => $stage['storage_path']]);
                }
            }, 'product_create');
        } catch (\Throwable $e) {
            $this->storage->discard($stage);
            throw $e;
        }

        return $request->expectsJson() ? response()->json(['revision' => $trip->fresh()->revision]) : to_route('trips.show', $trip)->with('success', 'Producto agregado');
    }

    public function update(ProductRequest $request, Trip $trip, Product $product)
    {
        $data = $request->safe()->except(['expected_revision', 'imagen', 'eliminar_imagen']);
        $stage = $request->hasFile('imagen') ? $this->storage->stage($request->file('imagen')) : null;
        try {
            $this->mutation->execute($trip, function ($current) use ($request, $data, $stage, $product) {
                $record = $current->products()->findOrFail($product->id);
                $record->update($data);
                if ($stage) {
                    $document = $this->storage->attach($stage, $current, 'product_image', $record);
                    $record->update(['document_id' => $document->id, 'imagen' => $stage['storage_path']]);
                } elseif ($request->boolean('eliminar_imagen')) {
                    $record->update(['document_id' => null, 'imagen' => null]);
                }
            }, 'product_update');
        } catch (\Throwable $e) {
            $this->storage->discard($stage);
            throw $e;
        }

        return $request->expectsJson()
            ? response()->json(['revision' => $trip->fresh()->revision])
            : to_route('trips.show', $trip)->with('success', 'Producto actualizado');
    }

    public function updatePrecioVenta(Request $request, Trip $trip, Product $product)
    {
        $this->authorize('update', $trip);
        $data = $request->validate(['precio_venta' => 'nullable|numeric|gt:0|max:9999999999.99|decimal:0,2', 'expected_revision' => 'required|integer|min:1']);
        unset($data['expected_revision']);
        $this->mutation->execute($trip, fn ($current) => $current->products()->findOrFail($product->id)->update($data), 'price_update');

        return to_route('trips.show', $trip)->with('success', 'Precio de venta guardado');
    }

    public function destroy(Request $request, Trip $trip, Product $product)
    {
        $this->authorize('update', $trip);
        $this->mutation->execute($trip, fn ($current) => $current->products()->findOrFail($product->id)->delete(), 'product_delete');

        return $request->expectsJson() ? response()->json(['revision' => $trip->fresh()->revision]) : to_route('trips.show', $trip)->with('success', 'Producto eliminado; sus documentos se conservan');
    }
}
