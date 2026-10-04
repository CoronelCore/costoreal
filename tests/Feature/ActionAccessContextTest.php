<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostReconciliation;
use App\Models\Document;
use App\Models\ExpenseCategory;
use App\Models\ImportDraft;
use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Access\AccessContext;
use App\Modules\Costing\Reconciliation\ReviewStoredCosts;
use App\Modules\Documents\ReadDocument;
use App\Modules\Documents\StoreDocument;
use App\Modules\Importations\Actions\CloseImportation;
use App\Modules\Importations\Actions\CreateImportation;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Importations\Actions\RecoverTrip;
use App\Modules\Importations\Creation\DraftPayload;
use App\Modules\Importations\Queries\ReadCreation;
use App\Modules\Reporting\ReportTrip;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActionAccessContextTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $owner): Trip
    {
        return Trip::create(['user_id' => $owner->id, 'nombre' => 'Actor explícito', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
    }

    private function product(Trip $trip)
    {
        return $trip->products()->create(['nombre' => 'Conservado', 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => '10', 'subtotal_clp' => '10', 'costo_final_total_clp' => '10', 'costo_final_unitario_clp' => '10']);
    }

    private function denied(callable $operation, string $exception = AuthorizationException::class): void
    {
        try {
            $operation();
            $this->fail('La operación debía rechazarse.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exception, $e);
        }
    }

    private function draft(User $owner, Trip $trip, array $payload, bool $completed = false): ImportDraft
    {
        return ImportDraft::create(['id' => (string) Str::uuid(), 'user_id' => $owner->id, 'trip_id' => $trip->id, 'step' => 'productos', 'revision' => 1,
            'payload' => $payload, 'last_write_key' => (string) Str::uuid(), 'last_write_hash' => hash('sha256', 'fixture'), 'completed_at' => $completed ? now() : null]);
    }

    public function test_explicit_actor_controls_costs_audit_and_baseline_under_another_http_user(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $this->product($trip);
        $other = User::factory()->create();
        $this->actingAs($other);
        $context = AccessContext::forActor($owner->id);
        app(MutateTrip::class)->execute($trip, fn ($current) => $current->products()->first()->update(['cantidad' => 2]), expectedRevision: 1, context: $context);
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame($owner->id, $trip->costRuns()->firstOrFail()->actor_id);
        $this->assertSame($owner->id, $trip->auditEvents()->firstOrFail()->actor_id);
        $this->assertSame($owner->id, LegacyCostBaseline::where('trip_id', $trip->id)->firstOrFail()->actor_id);
        $this->assertSame('20', $trip->costRuns()->first()->result['total']);
        $this->assertSame($other->id, auth()->id());
        $this->assertSame('20.00', (string) app(ReportTrip::class)->resolve($trip->fresh(), context: $context)->products->first()->costo_final_total_clp);
    }

    public function test_foreign_actor_is_rejected_before_callback_and_revision_claim(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $other = User::factory()->create();
        $this->actingAs($owner);
        $called = false;
        $this->denied(fn () => app(MutateTrip::class)->execute($trip, function () use (&$called) {
            $called = true;
        }, expectedRevision: 1, context: AccessContext::forActor($other->id)));
        $this->assertFalse($called);
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, $trip->auditEvents()->count());
        $this->assertSame(0, $trip->costRuns()->count());
    }

    public function test_worker_without_session_can_close_and_recover_with_money_and_actor_preserved(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $product = $this->product($trip);
        $context = AccessContext::forActor($owner->id);
        $this->assertNull(auth()->user());
        app(CloseImportation::class)->execute($trip, 1, $context);
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $money = $product->fresh()->getAttributes();
        app(RecoverTrip::class)->execute($trip->fresh(), 2, false, $context);
        app(RecoverTrip::class)->execute(Trip::withTrashed()->findOrFail($trip->id), 3, true, $context);
        $this->assertSame($money, $product->fresh()->getAttributes());
        $this->assertSame('finalizado', $trip->fresh()->estado);
        $this->assertSame(4, $trip->fresh()->revision);
        $this->assertSame(1, $trip->costRuns()->count());
        $this->assertSame([$owner->id, $owner->id, $owner->id], $trip->auditEvents()->orderBy('id')->pluck('actor_id')->all());
        $this->assertNull(auth()->user());
    }

    public function test_stale_worker_revision_leaves_every_financial_record_unchanged(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $product = $this->product($trip);
        $before = $product->fresh()->getAttributes();
        $this->denied(fn () => app(MutateTrip::class)->execute($trip, fn ($current) => $current->products()->first()->update(['cantidad' => 2]), expectedRevision: 99, context: AccessContext::forActor($owner->id)), ValidationException::class);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, LegacyCostBaseline::count());
        $this->assertSame(0, $trip->costRuns()->count());
    }

    public function test_missing_and_deleted_actor_cannot_stage_or_be_resolved(): void
    {
        Storage::fake('local');
        $this->denied(fn () => app(StoreDocument::class)->stage(UploadedFile::fake()->image('no-actor.png')));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $owner = User::factory()->create();
        $id = $owner->id;
        $owner->delete();
        $this->denied(fn () => AccessContext::forActor($id), ModelNotFoundException::class);
    }

    public function test_document_actor_parent_and_fresh_trip_state_are_enforced_outside_http(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $trip = $this->trip($owner);
        $context = AccessContext::forActor($owner->id);
        $foreign = AccessContext::forActor($other->id);
        $storage = app(StoreDocument::class);
        $stage = $storage->stage(UploadedFile::fake()->image('own.png'), $context);
        $this->denied(fn () => $storage->attach($stage, $trip, 'other', context: $foreign));
        $foreignTrip = $this->trip($other);
        $this->denied(fn () => $storage->attach($stage, $foreignTrip, 'other', context: $foreign));
        $this->denied(fn () => $storage->discard($stage, $foreign));
        $otherOwnTrip = $this->trip($owner);
        $wrongParent = $this->product($otherOwnTrip);
        $this->denied(fn () => $storage->attach($stage, $trip, 'product_image', $wrongParent, $context), HttpException::class);
        $trip->fresh()->update(['estado' => 'finalizado']);
        $this->denied(fn () => $storage->attach($stage, $trip, 'other', context: $context));
        $this->assertSame(0, Document::count());
        Storage::disk('local')->assertExists($stage['storage_path']);
        $storage->discard($stage, $context);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_explicit_upload_actor_read_scope_and_soft_delete_do_not_depend_on_session(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $context = AccessContext::forActor($owner->id);
        $this->actingAs(User::factory()->create());
        $stage = app(StoreDocument::class)->stage(UploadedFile::fake()->image('owned.png'), $context);
        $document = app(MutateTrip::class)->execute($trip, fn ($current) => app(StoreDocument::class)->attach($stage, $current, 'other', context: $context), 'document_upload', expectedRevision: 1, recalculate: false, context: $context);
        $this->assertEquals($owner->id, $document->uploaded_by);
        $this->assertSame($owner->id, $trip->auditEvents()->firstOrFail()->actor_id);
        $this->assertSame(0, $trip->costRuns()->count());
        auth()->logout();
        $response = app(ReadDocument::class)->read($context, $trip, $document);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->denied(fn () => app(ReadDocument::class)->read(AccessContext::forActor(User::factory()->create()->id), $trip, $document));
        $this->denied(fn () => app(ReadDocument::class)->read($context, $this->trip($owner), $document), ModelNotFoundException::class);
        $document->delete();
        $this->denied(fn () => app(ReadDocument::class)->read($context, $trip, $document), ModelNotFoundException::class);
        Storage::disk('local')->assertExists($document->storage_path);
    }

    public function test_creation_action_rejects_foreign_company_but_accepts_owned_inactive_company(): void
    {
        $owner = User::factory()->create();
        $foreign = Company::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Ajena', 'activa' => true]);
        $fields = ['nombre' => 'Contexto', 'company_id' => $foreign->id, 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900'];
        $this->denied(fn () => app(CreateImportation::class)->execute($owner, $fields), ValidationException::class);
        $this->assertSame(0, Trip::count());
        $own = Company::create(['user_id' => $owner->id, 'nombre' => 'Inactiva propia', 'activa' => false]);
        $trip = app(CreateImportation::class)->execute($owner, [...$fields, 'company_id' => $own->id]);
        $this->assertSame($owner->id, $trip->user_id);
        $this->assertSame($own->id, $trip->company_id);
    }

    public function test_completed_draft_is_authorized_before_redirecting_to_linked_trip(): void
    {
        $owner = User::factory()->create();
        $foreignTrip = $this->trip(User::factory()->create());
        $draft = $this->draft($owner, $foreignTrip, ['context' => ['nombre' => 'Legado']], true);
        $this->denied(fn () => app(ReadCreation::class)->read(AccessContext::forActor($owner->id), $draft->id));
        $this->actingAs($owner)->get('/trips/create?draft='.$draft->id)->assertForbidden();
        $this->assertSame($foreignTrip->id, $draft->fresh()->trip_id);
    }

    public function test_pending_draft_category_is_filtered_without_losing_editor_text_or_raw_payload(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $category = ExpenseCategory::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Privada ajena']);
        $payload = ['context' => ['nombre' => 'Borrador'], 'product' => null, 'expense' => ['fields' => ['expense_category_id' => $category->id, 'concepto' => 'Texto conservado', 'monto' => '12.34', 'expected_revision' => 1]]];
        $draft = $this->draft($owner, $trip, $payload);
        $data = app(DraftPayload::class)->data($draft, AccessContext::forActor($owner->id));
        $this->assertSame('', $data['payload']['expense']['fields']['expense_category_id']);
        $this->assertSame('12.34', $data['payload']['expense']['fields']['monto']);
        $this->assertSame('Texto conservado', $data['payload']['expense']['fields']['concepto']);
        $this->assertSame($payload, $draft->fresh()->payload);
        $this->denied(fn () => app(DraftPayload::class)->data($draft, AccessContext::forActor(User::factory()->create()->id)), HttpException::class);
    }

    public function test_reconciliation_without_http_requires_actor_and_records_that_actor(): void
    {
        $owner = User::factory()->create();
        $trip = $this->trip($owner);
        $this->product($trip);
        $this->denied(fn () => app(ReviewStoredCosts::class)->execute($trip, 1));
        $this->denied(fn () => app(ReviewStoredCosts::class)->execute($trip, 1, context: AccessContext::forActor(User::factory()->create()->id)));
        $this->assertSame(0, CostReconciliation::where('trip_id', $trip->id)->count());
        $result = app(ReviewStoredCosts::class)->execute($trip, 1, context: AccessContext::forActor($owner->id));
        $this->assertSame($owner->id, $result->actor_id);
        $this->assertSame(1, $trip->fresh()->revision);
    }
}
