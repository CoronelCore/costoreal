<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Creating an account from an owner's invitation, which works while open registration stays closed. */
class InvitationController extends Controller
{
    public const UNUSABLE = 'Esta invitación ya no sirve: se usó, venció o fue anulada. Pide un enlace nuevo a quien te invitó.';

    public function show(string $token): Response
    {
        $invitation = Invitation::findUsable($token);

        return Inertia::render('Auth/Invitation', [
            'token' => $token,
            'invitation' => $invitation ? ['name' => $invitation->name, 'email' => $invitation->email,
                'owner' => Installation::ownerName(), 'beta' => Installation::betaNotice()] : null,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            // The person confirms they read where their data lives and who administers the hosting.
            'understood' => ['accepted'],
        ], ['email.unique' => 'Ya hay una cuenta con ese correo. Si es tuya, inicia sesión.',
            'understood.accepted' => 'Marca la casilla para confirmar que leíste cómo se guardan tus datos.']);

        $user = DB::transaction(function () use ($token, $data) {
            // Locked so two tabs cannot turn one invitation into two accounts.
            $invitation = Invitation::query()->usable()->where('token_hash', Invitation::hash($token))->lockForUpdate()->first();
            if (! $invitation) {
                throw ValidationException::withMessages(['invitation' => self::UNUSABLE]);
            }
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $invitation->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('trips.index')->with('success', 'Tu cuenta quedó lista. Lo que registres aquí lo ves solo tú.');
    }
}
