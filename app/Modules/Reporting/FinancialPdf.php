<?php

namespace App\Modules\Reporting;

use App\Models\Trip;
use Brick\Math\BigDecimal;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;

/** Paginated vector report; no complete HTML table/DOM is held in memory. */
final class FinancialPdf
{
    private Canvas $canvas;

    private FontMetrics $metrics;

    private string $font;

    private string $bold;

    private float $y = 28;

    private float $width;

    private float $bottom;

    private const INK = [0.09, 0.16, 0.19];

    private const MUTED = [0.33, 0.40, 0.43];

    private const GREEN = [0.04, 0.36, 0.31];

    private const LINE = [0.85, 0.89, 0.90];

    public function __construct(private Trip $trip, private FinancialSummaryData $summary)
    {
        $pdf = new Dompdf(['defaultPaperSize' => 'a4', 'defaultPaperOrientation' => 'landscape',
            'isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false, 'isPhpEnabled' => false]);
        $this->canvas = $pdf->getCanvas();
        $this->metrics = $pdf->getFontMetrics();
        $this->font = $this->metrics->getFont('DejaVu Sans');
        $this->bold = $this->metrics->getFont('DejaVu Sans', 'bold');
        $this->width = $this->canvas->get_width() - 56;
        $this->bottom = $this->canvas->get_height() - 55;
    }

    public function render(): string
    {
        $this->text(28, $this->y, 'COSTO REAL / INFORME FINANCIERO', 10, true, self::GREEN);
        $this->y += 24;
        $this->paragraph($this->trip->nombre, 18, true);
        $state = match ($this->trip->estado) {
            'activo' => 'En curso', 'finalizado' => 'Cerrada', 'archivado' => 'Archivada', default => $this->trip->estado
        };
        $this->paragraph(($this->trip->company?->nombre ?? 'Sin empresa asignada').' / '.$state.' / Revisión '.$this->trip->revision, 9);
        $this->paragraph('Inicio: '.($this->trip->fecha_inicio?->format('d/m/Y') ?? 'Sin fecha').' / Fin: '.($this->trip->fecha_fin?->format('d/m/Y') ?? 'Sin fecha')
            .' / Generado: '.now()->format('d/m/Y H:i T'), 9);
        $this->paragraph('Tasa de referencia: '.DecimalFormatter::rate($this->trip->tipo_cambio).' CLP / '.$this->trip->moneda_trabajo, 9);
        if ($this->trip->report_provenance) {
            $this->paragraph($this->trip->report_provenance, 9);
        }
        if ($this->trip->descripcion) {
            $this->paragraph($this->trip->descripcion, 9);
        }
        $s = $this->summary->statistics;
        $this->table(['Concepto', 'Importe CLP'], [$this->width * .68, $this->width * .32], [
            ['Compra de productos', $this->money($s['total_productos_clp'])], ['Gastos de importación', $this->money($s['total_gastos_clp'])],
            ['Costo del lote', $this->money($s['costo_total'])], ['Gastos sin distribuir', $this->money($s['gastos_sin_asignar_clp'])],
        ], [1], 'Resumen financiero / CLP');
        $p = $this->summary->projection;
        $this->section('Proyección de venta / CLP', 150);
        $this->paragraph($p['priced_lines'].' de '.$p['lines'].' líneas con precio. Proyección; no representa ventas realizadas.', 9);
        $this->table(['Concepto', 'Proyección'], [$this->width * .68, $this->width * .32], [
            ['Ingreso del lote', $p['revenue'] === null ? 'Pendiente' : $this->money($p['revenue'])],
            ['Utilidad del lote', $p['profit'] === null ? 'Pendiente' : $this->money($p['profit'])],
            ['Margen sobre ventas', ! $p['complete'] || $p['margin'] === null ? 'Pendiente' : DecimalFormatter::format($p['margin'], 2).'%'],
            ...(! $p['complete'] ? [['Ingreso de líneas con precio', $this->money($p['covered_revenue'])], ['Utilidad de líneas con precio', $this->money($p['covered_profit'])]] : []),
        ], [1], 'Proyección de venta / CLP', false);
        if ($this->trip->products->isNotEmpty()) {
            $this->table(['Producto', 'Cant.', 'Pagado por unidad', 'Costo real por unidad', 'Precio de venta', 'Ganancia por unidad', 'Margen'],
                [$this->width - 630, 55, 120, 135, 115, 130, 75], $this->results(), [1, 2, 3, 4, 5, 6], 'Resultado por producto / CLP por unidad');
        }
        $this->table(['Producto', 'Cant.', 'Unitario original', 'Compra CLP', 'Gastos CLP', 'Total CLP', 'Unitario CLP'],
            [$this->width - 641, 55, 116, 127, 102, 137, 104], $this->products(), [1, 2, 3, 4, 5, 6], 'Productos y costeo / '.$this->trip->products->count().' '.($this->trip->products->count() === 1 ? 'línea' : 'líneas'));
        $this->table(['Categoría', 'Concepto', 'Monto original', 'Monto CLP'],
            [160, $this->width - 410, 130, 120], $this->expenses(), [2, 3], 'Gastos / '.$this->trip->expenses->count().' '.($this->trip->expenses->count() === 1 ? 'registro' : 'registros'));
        $this->paragraph('Los importes fraccionarios heredados se muestran con centésimas. El costo unitario se presenta con dos decimales; el total de cada línea conserva el importe registrado.', 8);
        $height = $this->canvas->get_height();
        $this->canvas->page_line(28, $height - 42, $this->width + 28, $height - 42, self::LINE, .5);
        $this->canvas->page_text(28, $height - 33, 'Importes registrados / CLP. Precio de venta proyectado; no representa ventas realizadas.', $this->font, 8, self::MUTED);
        $this->canvas->page_text($this->width - 84, $height - 33, 'Página {PAGE_NUM} / {PAGE_COUNT}', $this->font, 8, self::MUTED);

        return $this->canvas->output();
    }

    private function results(): iterable
    {
        foreach ($this->trip->products as $product) {
            $r = ProductResult::of($product);
            yield [$product->nombre, (string) $product->cantidad, DecimalFormatter::pesos($r['compra_unitaria_clp']),
                DecimalFormatter::pesos($r['costo_unitario_clp']),
                $r['precio_venta'] === null ? 'Sin precio' : DecimalFormatter::pesos($r['precio_venta']),
                $r['ganancia_unitaria_clp'] === null ? 'Pendiente' : DecimalFormatter::pesos($r['ganancia_unitaria_clp']),
                $r['margen'] === null ? 'Pendiente' : DecimalFormatter::format($r['margen'], 1).'%'];
        }
    }

    private function products(): iterable
    {
        foreach ($this->trip->products as $product) {
            yield [$product->nombre, (string) $product->cantidad, DecimalFormatter::format($product->costo_unitario, 2).' '.$product->moneda,
                $this->money($product->subtotal_clp), $this->money($product->gastos_prorrateados_clp), $this->money($product->costo_final_total_clp), DecimalFormatter::format($product->costo_final_unitario_clp, 2)];
        }
        if ($this->trip->products->isEmpty()) {
            yield ['Sin productos', '', '', '', '', '', ''];
        }
        $s = $this->summary->statistics;
        yield ['TOTAL CLP', '', '', $this->money($s['total_productos_clp']), $this->money($s['total_gastos_prorrateados_clp']), $this->money($s['costo_total_final_clp']), ''];
    }

    private function expenses(): iterable
    {
        foreach ($this->trip->expenses as $expense) {
            yield [$expense->category?->nombre ?? 'Sin categoría', $expense->concepto, DecimalFormatter::format($expense->monto, 2).' '.$expense->moneda, $this->money($expense->monto_clp)];
        }
        if ($this->trip->expenses->isEmpty()) {
            yield ['Sin gastos', '', '', ''];
        }
    }

    private function money(string $amount): string
    {
        $scale = BigDecimal::of($amount)->stripTrailingZeros()->getScale() > 0 ? 2 : 0;

        return '$'.DecimalFormatter::format($amount, $scale);
    }

    private function text(float $x, float $y, string $text, float $size = 9, bool $bold = false, array $color = self::INK): void
    {
        $this->canvas->text($x, $y, $text, $bold ? $this->bold : $this->font, $size, $color);
    }

    private function newPage(): void
    {
        $this->canvas->new_page();
        $this->y = 28;
        $this->text(28, $this->y, 'COSTO REAL / CONTINUACIÓN', 9, true, self::GREEN);
        $this->y += 19;
        $this->paragraph($this->trip->nombre, 9);
        $this->y += 8;
    }

    private function section(string $name, float $space = 65): void
    {
        if ($this->y + $space > $this->bottom) {
            $this->newPage();
        }
        $this->y += 14;
        $this->text(28, $this->y, $name, 12, true);
        $this->y += 25;
    }

    private function paragraph(string $text, float $size, bool $bold = false): void
    {
        foreach ($this->wrap($text, $this->width, $size, $bold) as $line) {
            if ($this->y + $size + 6 > $this->bottom) {
                $this->newPage();
            }
            $this->text(28, $this->y, $line, $size, $bold, $bold ? self::INK : self::MUTED);
            $this->y += $size + 5;
        }
        $this->y += 5;
    }

    private function wrap(string $text, float $width, float $size = 9, bool $bold = false): array
    {
        $font = $bold ? $this->bold : $this->font;
        $result = [];
        foreach (preg_split('/\R/u', $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph)) as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;
                if ($this->metrics->getTextWidth($candidate, $font, $size) <= $width) {
                    $line = $candidate;

                    continue;
                }
                if ($line !== '') {
                    $result[] = $line;
                }
                $line = '';
                foreach (mb_str_split($word) as $character) {
                    if ($line !== '' && $this->metrics->getTextWidth($line.$character, $font, $size) > $width) {
                        $result[] = $line;
                        $line = '';
                    }
                    $line .= $character;
                }
            }
            $result[] = $line;
        }

        return $result ?: [''];
    }

    private function layout(array $cells, array $widths, array $numeric, bool $bold, bool $header): array
    {
        $wrapped = [];
        $sizes = [];
        $font = $bold ? $this->bold : $this->font;
        foreach ($cells as $index => $cell) {
            $text = (string) $cell;
            $available = $widths[$index] - 12;
            if (! $header && in_array($index, $numeric, true)) {
                // Keep each financial amount intact, including certified large values.
                $measured = $this->metrics->getTextWidth($text, $font, 9);
                $sizes[$index] = $measured > $available ? 9 * $available / $measured : 9;
                $wrapped[$index] = [$text];
            } else {
                $sizes[$index] = 9;
                $wrapped[$index] = $this->wrap($text, $available, 9, $bold);
            }
        }

        return [$wrapped, $sizes, max(array_map('count', $wrapped)) * 14 + 12];
    }

    private function table(array $headings, array $widths, iterable $rows, array $numeric, string $title, bool $introduce = true): void
    {
        $headerHeight = $this->layout($headings, $widths, $numeric, true, true)[2];
        $first = true;
        foreach ($rows as $cells) {
            $emphasis = in_array($cells[0], ['Costo del lote', 'TOTAL CLP'], true);
            $height = $this->layout($cells, $widths, $numeric, $emphasis, false)[2];
            if ($first) {
                if ($this->y + ($introduce ? 39 : 0) + $headerHeight + $height > $this->bottom) {
                    $this->newPage();
                }
                if ($introduce) {
                    $this->section($title);
                }
                $this->row($headings, $widths, $numeric, true);
                $first = false;
            } elseif ($this->y + $height > $this->bottom) {
                $this->newPage();
                $this->section($title.' / continuación');
                $this->row($headings, $widths, $numeric, true);
            }
            $this->row($cells, $widths, $numeric, false, $emphasis);
        }
        $this->y += 8;
    }

    private function row(array $cells, array $widths, array $numeric, bool $header = false, bool $emphasis = false): void
    {
        $bold = $header || $emphasis;
        [$wrapped, $sizes, $height] = $this->layout($cells, $widths, $numeric, $bold, $header);
        if ($header || $emphasis) {
            $this->canvas->filled_rectangle(28, $this->y, $this->width, $height, $emphasis ? [0.92, 0.96, 0.94] : [0.94, 0.97, 0.97]);
        }
        $x = 28;
        foreach ($wrapped as $index => $lines) {
            foreach ($lines as $row => $line) {
                $left = $x + 6;
                if (in_array($index, $numeric, true)) {
                    $left = $x + $widths[$index] - 6 - $this->metrics->getTextWidth($line, $bold ? $this->bold : $this->font, $sizes[$index]);
                }
                $this->text($left, $this->y + 6 + $row * 14, $line, $sizes[$index], $bold);
            }
            $x += $widths[$index];
        }
        $this->y += $height;
        $this->canvas->line(28, $this->y, $this->width + 28, $this->y, self::LINE, .5);
    }
}
