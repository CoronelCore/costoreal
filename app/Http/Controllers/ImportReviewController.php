<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Modules\Importations\Review\ReviewImportation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportReviewController extends Controller
{
    public function show(Request $request, Trip $trip, ReviewImportation $review)
    {
        $this->authorize('view', $trip);
        $data = $request->validate(['expected_revision' => 'nullable|integer|min:1']);

        return DB::transaction(function () use ($trip, $data, $review) {
            $current = $trip->fresh();
            abort_unless($current, 404);
            $this->authorize('view', $current);
            if (isset($data['expected_revision']) && (int) $data['expected_revision'] !== $current->revision) {
                throw ValidationException::withMessages(['conflict' => 'Esta importación cambió. Recarga los datos guardados antes de revisar el cierre.']);
            }

            return response()->json($review->execute($current));
        });
    }
}
