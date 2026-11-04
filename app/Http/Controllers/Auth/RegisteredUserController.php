<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response|RedirectResponse
    {
        if (! Installation::registrationOpen()) {
            return $this->closed();
        }

        // Same notice as an invitation: whoever signs up learns where their data lives before creating the account.
        return Inertia::render('Auth/Register', ['owner' => Installation::ownerName(), 'beta' => Installation::betaNotice()]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Installation::registrationOpen()) {
            return $this->closed();
        }
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'understood' => ['accepted'],
        ], ['understood.accepted' => 'Marca la casilla para confirmar que leíste cómo se guardan tus datos.']);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('trips.index')->with('success', 'Tu cuenta quedó lista. Lo que registres aquí lo ves solo tú.');
    }

    private function closed(): RedirectResponse
    {
        return to_route('login')->with('status', 'Esta instalación no acepta cuentas nuevas. Pide acceso a quien la administra.');
    }
}
