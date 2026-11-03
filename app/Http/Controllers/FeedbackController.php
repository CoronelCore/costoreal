<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Support\Installation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A comment from inside the app, delivered to the owner's Personas panel. */
class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            // A page component and tab, such as "Trips/Show#gastos": never names or amounts.
            'screen' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z\/]+(#[a-z-]+)?$/'],
            'viewport_width' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], ['message.required' => 'Escribe tu comentario.', 'message.max' => 'El comentario puede tener hasta 2.000 caracteres.']);

        Feedback::create([...$data, 'user_id' => $request->user()->id, 'author_name' => $request->user()->name]);
        // Kept as a setting so the owner's menu badge costs no query on every page.
        Installation::put(Installation::UNREAD_FEEDBACK, (string) Feedback::whereNull('read_at')->count());
        $owner = Installation::ownerName();

        return back()->with('success', $owner ? "Gracias. Tu comentario le llegó a {$owner}." : 'Gracias. Tu comentario quedó guardado.');
    }
}
