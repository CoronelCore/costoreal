<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Access\AccessContext;
use App\Modules\Audit\ListAuditEvents;
use App\Modules\Importations\Queries\ReadImportation;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Reporting\ReportTrip;
use App\Modules\Scenarios\ListScenarios;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegacyReadIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function legacyTrip(): Trip
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($owner);
        $company = Company::create(['user_id' => $foreign->id, 'nombre' => 'Empresa privada ajena', 'activa' => true]);
        $category = ExpenseCategory::create(['user_id' => $foreign->id, 'nombre' => 'Categoría privada ajena']);
        $trip = Trip::create(['user_id' => $owner->id, 'company_id' => $company->id, 'nombre' => 'Vínculos heredados',
            'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $trip->expenses()->create(['concepto' => 'Gasto propio', 'expense_category_id' => $category->id, 'moneda' => 'CLP', 'monto' => '25', 'monto_clp' => '25']);

        return $trip;
    }

    public function test_control_detail_and_edit_hide_foreign_metadata_without_rewriting_legacy_links(): void
    {
        $trip = $this->legacyTrip();
        $before = DB::table('trips')->find($trip->id);
        $expense = DB::table('expenses')->where('trip_id', $trip->id)->first();
        $this->get('/trips')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trips.data.0.company', null)->where('trips.data.0.company_id', null));
        $this->get("/trips/{$trip->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trip.company', null)->where('trip.company_id', null)
            ->where('trip.expenses.0.category', null)->where('trip.expenses.0.expense_category_id', null)
            ->where('estadisticas.costo_total', '25.00'));
        $this->get("/trips/{$trip->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page->where('trip.company_id', null));
        $this->assertEquals($before, DB::table('trips')->find($trip->id));
        $this->assertEquals($expense, DB::table('expenses')->where('trip_id', $trip->id)->first());
    }

    public function test_current_and_historical_reports_hide_foreign_metadata_and_preserve_snapshot_and_money(): void
    {
        $trip = $this->legacyTrip();
        $snapshot = $trip->load(['products', 'expenses.category', 'company'])->toArray();
        $baseline = LegacyCostBaseline::create(['trip_id' => $trip->id, 'revision' => 1, 'snapshot' => $snapshot, 'fingerprint' => hash('sha256', json_encode($snapshot)), 'created_at' => now()]);
        $run = $trip->costRuns()->create(['revision' => 1, 'reason' => 'close', 'algorithm' => 'legacy-test',
            'input' => [], 'result' => [], 'snapshot' => $snapshot, 'input_hash' => hash('sha256', 'legacy-test'), 'created_at' => now()]);
        foreach ([[null, null], [null, $baseline->id], [$run->id, null]] as [$runId, $baselineId]) {
            $report = app(ReportTrip::class)->resolve($trip->fresh(), runId: $runId, baselineId: $baselineId);
            $this->assertNull($report->company);
            $this->assertNull($report->expenses[0]->category);
            $this->assertSame('25.00', FinancialSummaryData::fromTrip($report)->statistics['costo_total']);
            $html = view('exports.trip-pdf', ['trip' => $report, 'stats' => FinancialSummaryData::fromTrip($report)->statistics,
                'projection' => FinancialSummaryData::fromTrip($report)->projection])->render();
            $this->assertStringNotContainsString('Empresa privada ajena', $html);
            $this->assertStringNotContainsString('Categoría privada ajena', $html);
        }
        $this->assertSame($snapshot, $baseline->fresh()->snapshot);
        $this->assertSame($snapshot, $run->fresh()->snapshot);
    }

    public function test_owned_inactive_company_and_shared_or_owned_categories_remain_readable_in_history(): void
    {
        foreach (['shared', 'owned'] as $kind) {
            $trip = $this->legacyTrip();
            $company = Company::create(['user_id' => $trip->user_id, 'nombre' => 'Empresa propia inactiva', 'activa' => false]);
            $trip->update(['company_id' => $company->id]);
            $category = ExpenseCategory::create(['user_id' => $kind === 'shared' ? null : $trip->user_id, 'nombre' => 'Categoría autorizada']);
            $trip->expenses()->first()->update(['expense_category_id' => $category->id]);
            $snapshot = $trip->fresh()->load(['products', 'expenses.category', 'company'])->toArray();
            $baseline = LegacyCostBaseline::create(['trip_id' => $trip->id, 'revision' => 1, 'snapshot' => $snapshot, 'fingerprint' => hash('sha256', json_encode($snapshot)), 'created_at' => now()]);
            $report = app(ReportTrip::class)->resolve($trip->fresh(), baselineId: $baseline->id);
            $this->assertSame($company->nombre, $report->company->nombre);
            $this->assertSame($category->nombre, $report->expenses[0]->category->nombre);
        }
    }

    public function test_report_service_rejects_foreign_actor_even_when_called_outside_controller(): void
    {
        $trip = $this->legacyTrip();
        $foreign = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(ReportTrip::class)->resolve($trip, context: new AccessContext($foreign, null));
    }

    public function test_read_services_reject_explicit_foreign_context_before_querying_relations(): void
    {
        $trip = $this->legacyTrip();
        $context = new AccessContext(User::factory()->create(), null);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            foreach ([ReadImportation::class, ListAuditEvents::class, ListScenarios::class] as $service) {
                try {
                    $service === ReadImportation::class
                        ? app($service)->read($context, $trip, [])
                        : app($service)->read($context, $trip);
                    $this->fail('La lectura ajena debe rechazarse: '.$service);
                } catch (AuthorizationException) {
                    $this->assertSame([], DB::getQueryLog());
                }
            }
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_snapshot_metadata_without_ownership_evidence_is_hidden_without_changing_money(): void
    {
        $trip = $this->legacyTrip();
        $snapshot = $trip->load(['products', 'expenses.category', 'company'])->toArray();
        unset($snapshot['company']['user_id'], $snapshot['expenses'][0]['category']['user_id']);
        $baseline = LegacyCostBaseline::create(['trip_id' => $trip->id, 'revision' => 1, 'snapshot' => $snapshot,
            'fingerprint' => hash('sha256', json_encode($snapshot)), 'created_at' => now()]);
        $report = app(ReportTrip::class)->resolve($trip, baselineId: $baseline->id);
        $this->assertNull($report->company);
        $this->assertNull($report->expenses[0]->category);
        $this->assertSame('25.00', FinancialSummaryData::fromTrip($report)->statistics['costo_total']);
        $this->assertSame($snapshot, $baseline->fresh()->snapshot);
    }
}
