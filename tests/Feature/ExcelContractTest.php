<?php

namespace Tests\Feature;

use App\Exports\TripExport;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExcelContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_written_workbook_has_numbers_and_literal_untrusted_names(): void
    {
        $trip = Trip::create(['user_id' => User::factory()->create()->id, 'nombre' => '=HYPERLINK("https://example.invalid")', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $trip->products()->create(['nombre' => '=1+1', 'moneda' => 'USD', 'cantidad' => 3, 'costo_unitario' => '100', 'subtotal_clp' => 270000, 'gastos_prorrateados_clp' => 30, 'costo_final_total_clp' => 270030, 'costo_final_unitario_clp' => '90010', 'precio_venta' => '120000']);
        $path = tempnam(sys_get_temp_dir(), 'costoreal-xlsx-');
        try {
            file_put_contents($path, Excel::raw(new TripExport($trip), \Maatwebsite\Excel\Excel::XLSX));
            Cell::setValueBinder(new DefaultValueBinder);
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('Productos');
            $this->assertSame('=1+1', $sheet->getCell('A2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            foreach (['B2' => 3, 'D2' => 100, 'E2' => 270000, 'F2' => 30, 'G2' => 270030, 'H2' => 90010, 'I2' => 120000] as $address => $amount) {
                $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell($address)->getDataType(), $address);
                $this->assertEquals($amount, $sheet->getCell($address)->getValue());
            }
            $this->assertEqualsWithDelta(0.2499166667, $sheet->getCell('J2')->getValue(), 0.000000001);
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
