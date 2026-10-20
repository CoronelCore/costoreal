<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Reporting\FinancialPdf;
use App\Modules\Reporting\FinancialSummaryData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaginatedFinancialPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_loaded_report_paginates_without_queries_or_mutating_financial_values(): void
    {
        $trip = new Trip(['nombre' => 'Importación sintética · café y logística', 'estado' => 'activo',
            'tipo_cambio' => '900.12345678', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'revision' => 7]);
        $trip->setRelation('company', null);
        $trip->setRelation('products', new Collection(array_map(fn ($i) => new Product([
            'nombre' => 'Línea '.$i, 'cantidad' => 1, 'moneda' => 'CLP', 'costo_unitario' => '10.10',
            'subtotal_clp' => '10.10', 'gastos_prorrateados_clp' => '0.10', 'costo_final_total_clp' => '10.20',
            'costo_final_unitario_clp' => '10.20', 'precio_venta' => $i % 2 ? null : '11.50',
        ]), range(0, 999))));
        $trip->setRelation('expenses', new Collection(array_map(function ($i) {
            $expense = new Expense(['concepto' => 'Gasto '.$i, 'moneda' => 'CLP', 'monto' => '0.20', 'monto_clp' => '0.20']);
            $expense->setRelation('category', null);

            return $expense;
        }, range(0, 499))));
        $before = $trip->toArray();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $summary = FinancialSummaryData::fromTrip($trip);
            $pdf = (new FinancialPdf($trip, $summary))->render();
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame('10200.00', $summary->statistics['costo_total']);
            $this->assertSame(500, $summary->projection['priced_lines']);
            $this->assertNull($summary->projection['profit']);
            $this->assertStringStartsWith('%PDF-', $pdf);
            $pages = preg_match_all('~/Type\s*/Page\b~', $pdf);
            $this->assertGreaterThan(50, $pages);
            // Products appear twice: per-unit results and the costing table (about 169 pages).
            $this->assertLessThan(220, $pages);
            $this->assertLessThan(500000, strlen($pdf));
            $this->assertSame($before, $trip->toArray());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_pdf_response_supports_empty_nullable_reports_and_preserves_authorization(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::create(['user_id' => $owner->id, 'nombre' => 'Importación sin fecha ni empresa',
            'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $response = $this->actingAs($owner)->get("/trips/{$trip->id}/export/pdf")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->actingAs(User::factory()->create())->get("/trips/{$trip->id}/export/pdf")->assertForbidden();
        $this->assertSame(0, $trip->products()->count());
        $this->assertSame(0, $trip->expenses()->count());
    }
}
