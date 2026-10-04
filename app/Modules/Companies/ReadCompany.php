<?php

namespace App\Modules\Companies;

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Importations\Data\ImportationData;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReadCompany
{
    public function index(User $user): array
    {
        return $user->companies()->withCount([
            'trips' => fn ($q) => $q->where('user_id', $user->id),
            'trips as retained_trips_count' => fn ($q) => $q->withTrashed(),
        ])->latest()->get()->map(fn ($company) => $this->record($company))->all();
    }

    public function record(Company $company): array
    {
        $data = $company->only(['id', 'nombre', 'rut', 'descripcion', 'color', 'icono', 'activa']);
        if ($company->getAttribute('trips_count') !== null) {
            $data['trips_count'] = (int) $company->trips_count;
        }
        if ($company->getAttribute('retained_trips_count') !== null) {
            $data['can_delete'] = (int) $company->retained_trips_count === 0;
        }

        return $data;
    }

    public function show(User $user, Company $company): array
    {
        Gate::forUser($user)->authorize('view', $company);

        // Explicit company page scope, independent of the global dashboard selector.
        // The transaction keeps the aggregate and page in one SQLite read snapshot.
        return DB::transaction(function () use ($user, $company) {
            $base = Trip::where('user_id', $user->id)->where('company_id', $company->id)->withFinancialTotals();
            $aggregate = DB::query()->fromSub((clone $base)->toBase(), 'company_imports')
                ->selectRaw("COUNT(*) as count, COALESCE(SUM(total_productos_clp),0) as purchase, COALESCE(SUM(total_gastos_clp),0) as expenses, COALESCE(SUM(CASE WHEN estado='activo' THEN 1 ELSE 0 END),0) as active")->first();
            $total = BigDecimal::of((string) $aggregate->purchase)->plus((string) $aggregate->expenses);
            $count = (int) $aggregate->count;
            $trips = $base->withCount(['products', 'expenses', 'products as priced_count' => fn ($q) => $q->where('precio_venta', '>', 0)])
                ->latest()->orderByDesc('id')->paginate(20)->withQueryString()
                ->through(fn ($trip) => ImportationData::listing($trip->setRelation('company', $company)));
            $record = $this->record($company);
            $record['trips_count'] = $count;
            $record['can_delete'] = ! $company->trips()->withTrashed()->exists();

            return ['companyRecord' => $record, 'trips' => $trips,
                'metrics' => ['count' => $count, 'active' => (int) $aggregate->active, 'total' => (string) $total,
                    'average' => $count ? (string) $total->dividedBy($count, 2, RoundingMode::HALF_UP) : '0']];
        });
    }
}
