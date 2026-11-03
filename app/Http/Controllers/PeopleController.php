<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Installation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The owner's view of who uses this installation: never their data, only their access. */
class PeopleController extends Controller
{
    public function index(Request $request): Response
    {
        $ownerId = Installation::ownerId();

        return Inertia::render('People/Index', [
            'people' => User::query()->withCount('trips')->orderBy('created_at')->orderBy('id')->get()
                ->map(fn (User $person) => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'email' => $person->email,
                    'created_at' => $person->created_at?->toIso8601String(),
                    'last_seen_at' => $person->last_seen_at?->toIso8601String(),
                    'suspended' => $person->isSuspended(),
                    'is_owner' => $person->id === $ownerId,
                    'trips_count' => $person->trips_count,
                ]),
            'registration_open' => Installation::registrationOpen(),
        ]);
    }

    /** Open registration lets anyone with the address create an account; invitations are the safer way. */
    public function registration(Request $request): RedirectResponse
    {
        $data = $request->validate(['registration_open' => 'required|boolean']);
        Installation::setRegistrationOpen($request->boolean('registration_open'));

        return to_route('people.index')->with('success', $data['registration_open']
            ? 'Registro abierto: cualquiera con la dirección puede crear una cuenta.' : 'Registro cerrado: solo entran las personas invitadas.');
    }

    public function suspend(User $person): RedirectResponse
    {
        if ($person->id === Installation::ownerId()) {
            throw ValidationException::withMessages(['person' => 'La cuenta dueña no se puede suspender.']);
        }
        // A new remember token also ends "remember me" sign-ins on every device.
        $person->forceFill(['suspended_at' => now(), 'remember_token' => Str::random(60)])->save();

        return to_route('people.index')->with('success', "{$person->name} ya no puede entrar. Sus datos se conservan.");
    }

    public function restore(User $person): RedirectResponse
    {
        $person->forceFill(['suspended_at' => null])->save();

        return to_route('people.index')->with('success', "{$person->name} puede volver a entrar.");
    }

    /** Installations without e-mail: the owner hands the reset link over by chat instead. */
    public function passwordLink(User $person): RedirectResponse
    {
        $url = route('password.reset', ['token' => Password::broker()->createToken($person), 'email' => $person->email]);
        Inertia::flash('link', ['kind' => 'password', 'name' => $person->name, 'url' => $url,
            'hours' => intdiv((int) config('auth.passwords.users.expire'), 60)]);

        return to_route('people.index');
    }
}
