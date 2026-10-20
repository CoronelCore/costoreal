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

    /** Installed by the browser installer, or by the terminal (an account already exists). */
    public static function installed(): bool
    {
        try {
            return self::setting('installed_at') !== null || User::query()->exists();
        } catch (QueryException) {
            return false;
        }
    }

    public static function markInstalled(User $owner): void
    {
        self::put('owner_user_id', (string) $owner->id);
        self::put('installed_at', now()->toIso8601String());
        self::setRegistrationOpen(false);
        self::put('schema', self::latestMigration());
    }

    /** @return list<string> */
    public static function migrationNames(): array
    {
        $names = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')) ?: []);
        sort($names);

        return $names;
    }

    public static function latestMigration(): ?string
    {
        $names = self::migrationNames();

        return $names ? end($names) : null;
    }

    /**
     * Uploading a newer version over an installation leaves migrations to apply. The cheap check compares the
     * last migration applied through CostoReal; only when it differs is the migrations table read.
     */
    public static function pendingMigrations(): array
    {
        $latest = self::latestMigration();
        if ($latest === null || self::setting('schema') === $latest) {
            return [];
        }
        try {
            $repository = app('migrator')->getRepository();
            $pending = array_values(array_diff(self::migrationNames(), $repository->repositoryExists() ? $repository->getRan() : []));
        } catch (QueryException) {
            return [];
        }
        if (! $pending) {
            self::put('schema', $latest);
        }

        return $pending;
    }
}
