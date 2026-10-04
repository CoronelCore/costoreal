<?php

namespace App\Modules\Access;

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class AccessContext
{
    private ?Collection $companies = null;

    private ?Company $activeCompany = null;

    private bool $resolved = false;

    public function __construct(public readonly ?User $user, private readonly ?int $requestedCompanyId) {}

    public static function forRequest(Request $request): self
    {
        $context = $request->attributes->get('costoreal.access');
        if (! $context) {
            $id = $request->hasSession() ? $request->session()->get('active_company_id') : null;
            $context = new self($request->user(), $id ? (int) $id : null);
            $request->attributes->set('costoreal.access', $context);
        }

        return $context;
    }

    public function activeCompany(): ?Company
    {
        if (! $this->resolved) {
            $this->resolved = true;
            $this->activeCompany = $this->requestedCompanyId ? $this->companies()->firstWhere('id', $this->requestedCompanyId) : null;
        }

        return $this->activeCompany;
    }

    public function companies(): Collection
    {
        return $this->companies ??= $this->user?->companies()->where('activa', true)->orderBy('nombre')->get() ?? collect();
    }

    public function trips(): Builder
    {
        return Trip::query()->where('user_id', $this->user?->id ?? 0)->when($this->activeCompany(), fn ($q, $c) => $q->where('company_id', $c->id));
    }

    /** Reading an owned inactive company is valid; selecting it for new work is separate. */
    public function companyRelation(): array
    {
        return ['company' => fn ($query) => $query->where('user_id', $this->user?->id ?? 0)];
    }

    public function financialRelations(): array
    {
        return [...$this->companyRelation(), 'expenses.category' => fn ($query) => $query->visibleTo($this->user?->id ?? 0), 'products'];
    }

    /** Missing ownership evidence in older snapshots must not expose private metadata. */
    public function ownsMetadata(?array $metadata): bool
    {
        return $this->user && isset($metadata['user_id']) && (int) $metadata['user_id'] === (int) $this->user->id;
    }

    public function seesCategoryMetadata(?array $metadata): bool
    {
        return $this->user && $metadata && array_key_exists('user_id', $metadata)
            && ($metadata['user_id'] === null || $this->ownsMetadata($metadata));
    }
}
