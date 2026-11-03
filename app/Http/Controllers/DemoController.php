<?php

namespace App\Http\Controllers;

use App\Modules\Demo\CreateDemoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Loads the two fictional examples into the signed-in account, so a new person has something to explore. */
class DemoController extends Controller
{
    public function store(Request $request, CreateDemoData $demo): RedirectResponse
    {
        $created = $demo->execute($request->user());

        return to_route('trips.show', $created['trip'])->with('success',
            'Cargamos dos ejemplos ficticios: este viaje de compras a Brasil y una importación de accesorios. Cuando termines, mándalos a la papelera.');
    }
}
