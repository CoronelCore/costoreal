<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $user, array $extra = []): Trip
    {
        return Trip::create([...['user_id' => $user->id, 'nombre' => 'Expediente conservado', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => 900, 'estado' => 'activo'], ...$extra]);
    }

    public function test_active_closed_archived_and_trashed_imports_prevent_account_deletion_without_logout_or_data_loss(): void
    {
        foreach (['activo', 'finalizado', 'archivado', 'papelera'] as $state) {
            $user = User::factory()->create();
            $trip = $this->trip($user, ['estado' => $state === 'papelera' ? 'activo' : $state]);
            if ($state === 'papelera') {
                $trip->delete();
            }
            $before = Trip::withTrashed()->findOrFail($trip->id)->getAttributes();
            $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password'])->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'account');
            $this->assertAuthenticatedAs($user);
            $this->assertNotNull($user->fresh());
            $this->assertSame($before, Trip::withTrashed()->findOrFail($trip->id)->getAttributes());
            $readiness = $this->get('/profile')->assertOk()->viewData('page')['props']['accountDeletion'];
            $this->assertFalse($readiness['can_delete']);
        }
    }

    public function test_empty_account_with_unused_private_categories_can_be_deleted_without_sharing_them(): void
    {
        $user = User::factory()->create();
        $category = ExpenseCategory::create(['user_id' => $user->id, 'nombre' => 'Nombre privado']);
        $base = ExpenseCategory::create(['nombre' => 'Base protegida']);
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('expense_categories', ['id' => $base->id, 'user_id' => null]);
    }

    public function test_legacy_foreign_links_to_owned_company_or_category_preserve_all_accounts_and_records(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $company = Company::create(['user_id' => $user->id, 'nombre' => 'Empresa propia']);
        $trip = $this->trip($foreign, ['company_id' => $company->id]);
        $this->actingAs($user)->deleteJson('/profile', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertSame($company->id, $trip->fresh()->company_id);
        DB::table('trips')->where('id', $trip->id)->update(['company_id' => null]);
        $category = ExpenseCategory::create(['user_id' => $user->id, 'nombre' => 'Categoría heredada']);
        $trip->expenses()->create(['expense_category_id' => $category->id, 'concepto' => 'Legado', 'moneda' => 'CLP', 'monto' => 10, 'monto_clp' => 10]);
        $this->deleteJson('/profile', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertNotNull($user->fresh());
        $this->assertDatabaseHas('expenses', ['trip_id' => $trip->id, 'expense_category_id' => $category->id]);
    }

    public function test_database_restricts_user_cascade_and_company_deletion_never_removes_imports(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['user_id' => $user->id, 'nombre' => 'Empresa']);
        $trip = $this->trip($user, ['company_id' => $company->id]);
        $trip->delete();
        try {
            DB::table('users')->where('id', $user->id)->delete();
            $this->fail('The database must prevent destructive user cascade.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
        DB::table('companies')->where('id', $company->id)->delete();
        $kept = Trip::withTrashed()->findOrFail($trip->id);
        $this->assertNull($kept->company_id);
        $this->assertSame($user->id, $kept->user_id);
        $this->assertNotNull($kept->deleted_at);
    }

    public function test_historic_actor_cannot_be_lost_by_deleting_a_currently_empty_account(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip(User::factory()->create());
        $audit = AuditEvent::create(['trip_id' => $trip->id, 'actor_id' => $user->id, 'action' => 'update', 'revision' => 1, 'before' => [], 'after' => [], 'created_at' => now()]);
        $this->actingAs($user)->deleteJson('/profile', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertSame($user->id, $audit->fresh()->actor_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_legacy_provenance_and_document_uploader_keep_their_identity_when_account_has_no_own_imports(): void
    {
        $trip = $this->trip(User::factory()->create());
        $references = [
            'legacy_cost_baselines' => ['actor_id', ['revision' => 1, 'fingerprint' => str_repeat('a', 64), 'snapshot' => '{}', 'created_at' => now()]],
            'cost_reconciliations' => ['actor_id', ['base_revision' => 1, 'fingerprint' => str_repeat('b', 64), 'snapshot' => '{}', 'comparison' => '{}', 'created_at' => now()]],
            'documents' => ['uploaded_by', ['filename' => 'historico.pdf', 'storage_path' => 'documents/test', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('c', 64), 'kind' => 'other', 'deleted_at' => now()]],
        ];
        foreach ($references as $table => [$column, $values]) {
            $user = User::factory()->create();
            $id = DB::table($table)->insertGetId(['trip_id' => $trip->id, $column => $user->id, ...$values]);
            $this->actingAs($user)->deleteJson('/profile', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');
            $this->assertSame($user->id, DB::table($table)->where('id', $id)->value($column));
            $this->assertAuthenticatedAs($user);
            $this->assertFalse($this->get('/profile')->viewData('page')['props']['accountDeletion']['can_delete']);
        }
    }
}
