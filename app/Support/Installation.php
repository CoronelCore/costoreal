<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\QueryException;

/** Installation-wide state: who owns this copy of CostoReal and whether others may register. */
final class Installation
{
    /** Container keys: settings are read once per request instead of once per question. */
    private const SETTINGS = 'costoreal.installation.settings';

    private const OWNER = 'costoreal.installation.owner';

    /** Comments the owner has not opened yet, kept here so the menu badge needs no extra query. */
    public const UNREAD_FEEDBACK = 'unread_feedback';

    public static function setting(string $key, ?string $default = null): ?string
    {
        return self::settings()[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        app()->instance(self::SETTINGS, [...self::settings(), $key => $value]);
        app()->forgetInstance(self::OWNER);
    }

    /** Called at the start of every request (GuardInstallation), so a long-lived process never serves stale values. */
    public static function flush(): void
    {
        app()->forgetInstance(self::SETTINGS);
        app()->forgetInstance(self::OWNER);
    }

    /** @return array<string, string|null> */
    private static function settings(): array
    {
        if (! app()->bound(self::SETTINGS)) {
            try {
                $settings = AppSetting::query()->pluck('value', 'key')->all();
            } catch (QueryException) {
                // Before the first migration there is no settings table yet.
                return [];
            }
            app()->instance(self::SETTINGS, $settings);
        }

        return app(self::SETTINGS);
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

    /** Invited accounts see a "test version" band unless the owner turns it off (for example, a team in production). */
    public static function betaNotice(): bool
    {
        return self::setting('beta_notice') !== '0';
    }

    public static function setBetaNotice(bool $on): void
    {
        self::put('beta_notice', $on ? '1' : '0');
    }

    public static function ownerName(): ?string
    {
        $owner = self::ownerId();

        return $owner === null ? null : User::query()->whereKey($owner)->value('name');
    }

    /** The account created when installing; older installs fall back to the first account. */
    public static function ownerId(): ?int
    {
        if (app()->bound(self::OWNER)) {
            return app(self::OWNER);
        }
        $owner = self::setting('owner_user_id') ?? User::query()->min('id');
        if ($owner === null) {
            // Not remembered: the first account may be created later in this same request.
            return null;
        }
        app()->instance(self::OWNER, (int) $owner);

        return (int) $owner;
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
