<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    public const DAYS = 7;

    protected $fillable = ['name', 'email', 'token_hash', 'invited_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return array{0: self, 1: string} The invitation and the plain token, which is never stored. */
    public static function issue(string $name, string $email, User $owner): array
    {
        $token = Str::random(40);
        $invitation = self::create(['name' => $name, 'email' => $email, 'token_hash' => self::hash($token),
            'invited_by' => $owner->id, 'expires_at' => now()->addDays(self::DAYS)]);

        return [$invitation, $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findUsable(string $token): ?self
    {
        return self::query()->usable()->where('token_hash', self::hash($token))->first();
    }

    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }
}
