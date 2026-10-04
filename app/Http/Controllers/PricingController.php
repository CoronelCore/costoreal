<?php

namespace App\Http\Controllers;

use App\Http\Requests\TargetPriceRequest;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Pricing\Actions\PreviewPrices;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingController extends Controller
{
    public function __construct(private PreviewPrices $preview, private MutateTrip $mutation) {}

    public function preview(TargetPriceRequest $request, Trip $trip)
    {
        $policy = $request->safe()->except('expected_revision');
        $result = DB::transaction(function () use ($trip, $request, $policy) {
            $current = $trip->fresh();
            if ($current->revision !== (int) $request->expected_revision) {
                throw ValidationException::withMessages(['conflict' => 'La importación cambió. Recarga y genera una vista previa con la revisión vigente.']);
            }

            return $this->preview->execute($current, $policy);
        });

        return response()->json($result);
    }

    public function apply(TargetPriceRequest $request, Trip $trip)
    {
        $policy = $request->safe()->except('expected_revision');
        $this->mutation->execute($trip, function ($current) use ($policy) {
            $result = $this->preview->execute($current, $policy);
            $records = $current->products->keyBy('id');
            $rows = [];
            foreach ($result['lines'] as $line) {
                $rows[] = $records[$line['id']]->fill(['precio_venta' => $line['price']])->getAttributes();
            }
            foreach (array_chunk($rows, 30) as $chunk) {
                Product::upsert($chunk, ['id'], ['precio_venta']);
            }
            DB::table('pricing_batches')->insert(['trip_id' => $current->id, 'actor_id' => auth()->id(), 'revision' => $current->revision + 1,
                'policy' => json_encode($policy, JSON_THROW_ON_ERROR), 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }, 'price_target');

        return to_route('trips.show', $trip)->with('success', 'Precios aplicados; objetivo y comisión conservados en el historial');
    }
}
