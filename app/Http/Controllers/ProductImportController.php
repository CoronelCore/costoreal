<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductImportRequest;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Importations\Importing\BulkImportData;
use App\Modules\Importations\Importing\PrepareProductImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImportController extends Controller
{
    public function __construct(private PrepareProductImport $prepare, private MutateTrip $mutation) {}

    public function preview(ProductImportRequest $request, Trip $trip)
    {
        $current = $trip->fresh();
        $this->authorize('update', $current);
        $this->checkRevision($request, $current);

        return response()->json(BulkImportData::preview($this->prepare->execute($current, $request->source(), $request->settings())));
    }

    public function apply(ProductImportRequest $request, Trip $trip)
    {
        $source = $request->source();
        $settings = $request->settings();
        $hash = $this->prepare->fingerprint($source, $settings);
        $existing = DB::table('product_import_batches')->where('trip_id', $trip->id)->where('idempotency_key', $request->idempotency_key)->first();
        if ($existing) {
            if (! hash_equals($existing->input_hash, $hash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'Esta clave corresponde a otro contenido. Genera una nueva vista previa.']);
            }

            return to_route('trips.show', $trip)->with('success', 'Este lote ya fue importado; no se duplicaron los productos');
        }
        $this->mutation->execute($trip, function ($current) use ($request, $source, $settings, $hash) {
            $preview = $this->prepare->execute($current, $source, $settings);
            if (! $preview['can_apply']) {
                throw ValidationException::withMessages(['import' => "El lote tiene {$preview['invalid_count']} filas inválidas o errores de costeo. Corrige la vista previa; no se importó ninguna fila."]);
            }
            $batch = DB::table('product_import_batches')->insertGetId(['trip_id' => $current->id, 'actor_id' => auth()->id(), 'idempotency_key' => $request->idempotency_key,
                'input_hash' => $hash, 'revision_before' => $current->revision, 'revision_after' => $current->revision + 1,
                'imported_count' => $preview['count'], 'created_at' => now()]);
            $rows = array_map(fn ($row) => [...$row['data'], 'trip_id' => $current->id, 'product_import_batch_id' => $batch,
                'subtotal_clp' => 0, 'gastos_prorrateados_clp' => 0, 'costo_final_total_clp' => 0, 'costo_final_unitario_clp' => 0,
                'rate_mode' => 'estimated', 'rate_source' => 'trip-reference', 'created_at' => now(), 'updated_at' => now()], $preview['rows']);
            foreach (array_chunk($rows, 30) as $chunk) {
                Product::insert($chunk);
            }
            $ids = Product::where('product_import_batch_id', $batch)->orderBy('id')->pluck('id')->all();
            DB::table('product_import_batches')->where('id', $batch)->update(['product_ids' => json_encode($ids, JSON_THROW_ON_ERROR)]);
        }, 'products_import');

        return to_route('trips.show', $trip)->with('success', 'Lote importado y costos recalculados');
    }

    private function checkRevision(ProductImportRequest $request, Trip $trip): void
    {
        if ($trip->revision !== (int) $request->expected_revision) {
            throw ValidationException::withMessages(['conflict' => 'La importación cambió. Recarga y vuelve a revisar el lote antes de importarlo.']);
        }
    }
}
