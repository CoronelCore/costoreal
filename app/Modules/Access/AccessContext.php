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
}
