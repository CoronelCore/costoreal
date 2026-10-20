<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\QueryException;

/** Installation-wide state: who owns this copy of CostoReal and whether others may register. */
final class Installation
{
    public static function setting(string $key, ?string $default = null): ?string
    {
        try {
            return AppSetting::find($key)?->value ?? $default;
        } catch (QueryException) {
            // Before the first migration there is no settings table yet.
            return $default;
        }
    }

    public static function put(string $key, ?string $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Closed unless the owner opens it: a public install must not let strangers use the hosting. */
    public static function registrationOpen(): bool
    {
        return self::setting('registration_open') === '1';
    }

    public static function setRegistrationOpen(bool $open): void
    {
        self::put('registration_open', $open ? '1' : '0');
    }

    /** The account created when installing; older installs fall back to the first account. */
    public static function ownerId(): ?int
    {
        $owner = self::setting('owner_user_id') ?? User::query()->min('id');

        return $owner === null ? null : (int) $owner;
    }

    public static function isOwner(?User $user): bool
    {
        return $user !== null && $user->id === self::ownerId();
    }
}
