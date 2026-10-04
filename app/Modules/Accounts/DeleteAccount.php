<?php

namespace App\Modules\Accounts;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteAccount
{
    public const RETAINED = 'Tu cuenta conserva expedientes o registros asociados. La papelera mantiene su recuperación; no permite eliminar la cuenta ni sus respaldos.';

    public function readiness(User $user): array
    {
        $retained = Trip::withTrashed()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('company_id', $user->companies()->select('id')))->exists()
            || Expense::whereIn('expense_category_id', ExpenseCategory::where('user_id', $user->id)->select('id'))->exists();
        if (! $retained) {
            foreach (['audit_events' => 'actor_id', 'cost_runs' => 'actor_id', 'cost_scenarios' => 'actor_id',
                'pricing_batches' => 'actor_id', 'product_import_batches' => 'actor_id',
                'legacy_cost_baselines' => 'actor_id', 'cost_reconciliations' => 'actor_id', 'documents' => 'uploaded_by'] as $table => $column) {
                if (DB::table($table)->where($column, $user->id)->exists()) {
                    $retained = true;
                    break;
                }
            }
        }

        return ['can_delete' => ! $retained, 'reason' => $retained ? self::RETAINED : null];
    }

    public function execute(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $this->readiness($current)['can_delete']) {
                    throw ValidationException::withMessages(['account' => self::RETAINED])->errorBag('userDeletion');
                }
                // Unused personal categories must not become shared categories or block an empty-account deletion.
                ExpenseCategory::where('user_id', $user->id)->delete();
                $current->delete();
            });
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '23503'], true)) {
                throw ValidationException::withMessages(['account' => self::RETAINED])->errorBag('userDeletion');
            }
            throw $e;
        }
    }
}
