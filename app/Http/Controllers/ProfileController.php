<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Modules\Accounts\DeleteAccount;
use App\Modules\Backups\CreateBackup;
use App\Modules\Backups\DatabaseCopy;
use App\Support\Installation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'emailVerified' => $request->user()->email_verified_at !== null,
            'status' => session('status'),
            'accountDeletion' => app(DeleteAccount::class)->readiness($request->user()),
            'installation' => Installation::isOwner($request->user()) ? ['registration_open' => Installation::registrationOpen(),
                'backup_includes_database' => app(DatabaseCopy::class)->isSqlite(), 'last_backup_at' => Installation::setting('last_backup_at')] : null,
        ]);
    }

    /** The whole installation's data, so only its owner can download it. */
    public function backup(Request $request, CreateBackup $backup): BinaryFileResponse
    {
        abort_unless(Installation::isOwner($request->user()), 403);
        $file = $backup->execute();

        return response()->download($file['path'], $file['name'], ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** Only the owner decides whether other people may create accounts on this installation. */
    public function updateRegistration(Request $request): RedirectResponse
    {
        abort_unless(Installation::isOwner($request->user()), 403);
        $data = $request->validate(['registration_open' => 'required|boolean']);
        Installation::setRegistrationOpen($request->boolean('registration_open'));

        return Redirect::route('profile.edit')->with('status', $data['registration_open'] ? 'registration-opened' : 'registration-closed');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        app(DeleteAccount::class)->execute($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
