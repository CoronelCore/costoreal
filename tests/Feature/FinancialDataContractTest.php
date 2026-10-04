<?php

namespace Tests\Feature;

use App\Exports\TripExport;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Reporting\DecimalFormatter;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Reporting\ReportTrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FinancialDataContractTest extends TestCase
{
    use RefreshDatabase;

    private function trip(): Trip
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return Trip::create(['user_id' => $user->id, 'nombre' => 'Contrato de lectura',
            'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900.12345678', 'estado' => 'activo']);
    }

    private function add(Trip $trip, string $currency = 'USD', string $cost = '100', array $extra = []): int
    {
        $this->post("/trips/{$trip->id}/products", ['nombre' => 'Producto', 'moneda' => $currency, 'cantidad' => 3,
            'costo_unitario' => $cost, 'expected_revision' => $trip->fresh()->revision, ...$extra])->assertSessionHasNoErrors();

        return $trip->products()->latest('id')->value('id');
    }

    public function test_detail_and_conflict_read_expose_decimal_text_and_explicit_safe_fields(): void
    {
        Storage::fake('local');
        $trip = $this->trip();
        $id = $this->add($trip, extra: ['rate_mode' => 'paid', 'applied_rate' => '950.12345678', 'rate_source' => 'Banco',
            'rate_date' => '2026-10-03', 'weight' => '2.12345678', 'volume' => '0.5', 'imagen' => UploadedFile::fake()->image('private.jpg')]);
        $this->post("/trips/{$trip->id}/expenses", ['concepto' => 'Gasto', 'moneda' => 'CLP', 'monto' => '10',
            'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $this->get("/trips/{$trip->id}")->assertInertia(fn (Assert $p) => $p
            ->where('trip.tipo_cambio', '900.12345678')->where('trip.products.0.applied_rate', '950.12345678')
            ->where('trip.products.0.weight', '2.12345678')->where('trip.products.0.volume', '0.5')
            ->where('trip.products.0.precio_venta', null)->where('trip.expenses.0.monto_clp', '10.00')
            ->where('trip.expenses.0.category', null)->where('trip.documents.0.size', fn ($v) => is_int($v))
            ->missing('trip.user_id')->missing('trip.products.0.imagen')->missing('trip.products.0.trip_id')
            ->missing('trip.expenses.0.comprobante')->missing('trip.documents.0.storage_path')->missing('trip.documents.0.trip_id')
            ->etc());
        $read = $this->getJson("/trips/{$trip->id}/products/{$id}")->assertOk()->json();
        $this->assertSame('950.12345678', $read['product']['applied_rate']);
        $this->assertSame('2.12345678', $read['product']['weight']);
        $this->assertSame('0.5', $read['product']['volume']);
        $this->assertSame(3, $read['product']['cantidad']);
        $this->assertArrayNotHasKey('document_id', $read['product']);
        $this->assertArrayNotHasKey('imagen', $read['product']);
    }

    public function test_complete_summary_includes_unassigned_expenses_and_keeps_a_real_loss(): void
    {
        $trip = $this->trip();
        $id = $this->add($trip, 'CLP', '0');
        $this->patch("/trips/{$trip->id}/products/{$id}/precio-venta", ['precio_venta' => '5', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/expenses", ['concepto' => 'Sin base', 'moneda' => 'CLP', 'monto' => '20',
            'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $summary = FinancialSummaryData::fromTrip($trip->fresh());
        $this->assertSame('20.00', $summary->statistics['costo_total']);
        $this->assertSame('20.00', $summary->statistics['gastos_sin_asignar_clp']);
        $this->assertSame('-5', $summary->projection['profit']);
        $this->assertSame('-33.33', $summary->projection['margin']);
        $this->get("/trips/{$trip->id}")->assertInertia(fn (Assert $p) => $p
            ->where('estadisticas', $summary->statistics)->where('projection', $summary->projection)->etc());
        $rows = (new TripExport($trip->fresh()))->sheets()[0]->array();
        $this->assertSame(20.0, $rows[14][1]);
        $this->assertSame(-5.0, $rows[21][1]);
        $html = view('exports.trip-pdf', ['trip' => $trip, 'stats' => $summary->statistics, 'projection' => $summary->projection])->render();
        $this->assertStringContainsString('$-5', $html);
        $this->assertStringContainsString('-33,33%', $html);
        $this->assertStringContainsString('900,12345678', $html);
    }

    public function test_incomplete_price_coverage_stays_null_in_workbook_and_pdf(): void
    {
        $trip = $this->trip();
        $this->add($trip);
        $summary = FinancialSummaryData::fromTrip($trip->fresh());
        $this->assertNull($summary->projection['profit']);
        $rows = (new TripExport($trip->fresh()))->sheets()[0]->array();
        $this->assertNull($rows[20][1]);
        $this->assertNull($rows[21][1]);
        $this->assertNull($rows[22][1]);
        $this->assertSame('Pendiente: faltan precios', $rows[23][1]);
        $html = view('exports.trip-pdf', ['trip' => $trip, 'stats' => $summary->statistics, 'projection' => $summary->projection])->render();
        $this->assertStringContainsString('Ingreso del lote</td><td class="num">Pendiente', $html);
        $this->assertStringContainsString('Utilidad del lote</td><td class="num">Pendiente', $html);
    }

    public function test_historical_summary_and_numeric_workbook_ignore_live_edits_without_extra_reads(): void
    {
        $trip = $this->trip();
        $id = $this->add($trip);
        $this->patch("/trips/{$trip->id}/products/{$id}/precio-venta", ['precio_venta' => '60000', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $run = $trip->costRuns()->where('reason', 'close')->firstOrFail();
        $this->patch("/trips/{$trip->id}/reopen", ['reason' => 'Corregir una nueva operación documentada', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $this->put("/trips/{$trip->id}/products/{$id}", ['nombre' => 'Costo nuevo', 'moneda' => 'USD', 'cantidad' => 3,
            'costo_unitario' => '200', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $historical = app(ReportTrip::class)->resolve($trip->fresh(), $run->id);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $summary = FinancialSummaryData::fromTrip($historical);
        $this->assertSame('270037.00', $summary->statistics['costo_total']);
        $this->assertSame('-90037', $summary->projection['profit']);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame('540074.00', FinancialSummaryData::fromTrip($trip->fresh())->statistics['costo_total']);
        $path = tempnam(sys_get_temp_dir(), 'costoreal-summary-');
        try {
            file_put_contents($path, Excel::raw(new TripExport($historical), \Maatwebsite\Excel\Excel::XLSX));
            Cell::setValueBinder(new DefaultValueBinder);
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('Resumen');
            foreach (['B15' => 270037, 'B21' => 180000, 'B22' => -90037] as $address => $expected) {
                $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell($address)->getDataType());
                $this->assertEquals($expected, $sheet->getCell($address)->getValue());
            }
            $this->assertSame('0.0%', $sheet->getStyle('B23')->getNumberFormat()->getFormatCode());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_pdf_decimal_format_preserves_certified_large_values_and_half_up_rounding(): void
    {
        $this->assertSame('9.000.000.000.000.000,02', DecimalFormatter::format('9000000000000000.015', 2));
        $this->assertSame('-1.234,57', DecimalFormatter::format('-1234.565', 2));
        $this->assertSame('0,00', DecimalFormatter::format('-0.004', 2));
        $this->assertSame('0,01', DecimalFormatter::format('0.005', 2));
    }
}
