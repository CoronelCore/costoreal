<?php

namespace App\Modules\Importations\Importing;

use App\Models\Product;
use App\Models\Trip;
use App\Services\ProrrateoService;
use App\Support\ChileanNumber;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PrepareProductImport
{
    public function __construct(private DelimitedReader $reader, private ProrrateoService $costing) {}

    public function execute(Trip $trip, string $source, array $settings): array
    {
        try {
            $raw = $this->reader->read($source, $settings['delimiter']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['source_text' => $e->getMessage()]);
        }
        $header = $settings['has_header'] ? array_shift($raw) : null;
        if (! $raw || count($raw) > 1000) {
            throw ValidationException::withMessages(['source_text' => 'Incluye entre 1 y 1.000 filas de productos, además del encabezado opcional.']);
        }
        $mapping = $settings['mapping'];
        $mapped = array_filter($mapping, fn ($index) => $index !== null && $index !== '');
        if (count(array_unique($mapped)) !== count($mapped)) {
            throw ValidationException::withMessages(['mapping' => 'Cada campo debe usar una columna diferente.']);
        }
        $columns = max([count($header ?? []), ...array_map('count', $raw)]);
        $rows = [];
        foreach ($raw as $index => $cells) {
            $data = [];
            foreach (['nombre', 'cantidad', 'moneda', 'costo_unitario', 'weight', 'volume'] as $field) {
                $column = $mapping[$field] ?? null;
                $data[$field] = $column !== null && $column !== '' ? ($cells[(int) $column] ?? '') : null;
            }
            $data['moneda'] = $data['moneda'] ?: $settings['default_currency'];
            $data['weight'] = $data['weight'] === '' ? null : $data['weight'];
            $data['volume'] = $data['volume'] === '' ? null : $data['volume'];
            $data = [...$data, ...ChileanNumber::fields($data, ['cantidad', 'costo_unitario', 'weight', 'volume'])];
            $validator = Validator::make($data, ['nombre' => 'required|string|max:255', 'cantidad' => 'required|integer|min:1|max:1000000',
                'moneda' => 'required|in:CLP,'.$trip->moneda_trabajo, 'costo_unitario' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
                'weight' => 'nullable|numeric|min:0|max:999999999|decimal:0,8', 'volume' => 'nullable|numeric|min:0|max:999999999|decimal:0,8'],
                ['required' => 'Completa :attribute.', 'string' => ':Attribute debe ser texto.', 'integer' => ':Attribute debe ser un número entero.',
                    'numeric' => ':Attribute debe ser un número, por ejemplo 1.250,50.', 'min' => ['numeric' => ':Attribute debe ser al menos :min.'],
                    'max' => ['numeric' => ':Attribute no puede superar :max.', 'string' => ':Attribute no puede superar :max caracteres.'],
                    'in' => ':Attribute debe ser CLP o '.$trip->moneda_trabajo.'.', 'decimal' => ':Attribute admite :decimal decimales.'],
                ['nombre' => 'el nombre del producto', 'cantidad' => 'la cantidad', 'moneda' => 'la moneda',
                    'costo_unitario' => 'el costo unitario', 'weight' => 'el peso total', 'volume' => 'el volumen total']);
            $errors = $validator->errors()->toArray();
            if (! $errors) {
                $data['cantidad'] = (int) $data['cantidad'];
                $data['costo_unitario'] = (string) BigDecimal::of($data['costo_unitario'])->toScale(2);
            }
            $rows[] = ['row' => $index + ($settings['has_header'] ? 2 : 1), 'data' => $data, 'errors' => $errors];
        }
        $errorCount = count(array_filter($rows, fn ($row) => $row['errors']));
        $costErrors = [];
        $cost = null;
        if (! $errorCount) {
            $trip->loadMissing(['products', 'expenses']);
            $candidate = clone $trip;
            $new = collect($rows)->map(fn ($row) => new Product([...$row['data'], 'rate_mode' => 'estimated', 'rate_source' => 'trip-reference']));
            // id is guarded during persistence; assign synthetic identifiers only to unsaved previews.
            foreach ($new as $index => $product) {
                $product->id = -($index + 1);
            }
            $candidate->setRelation('products', $trip->products->concat($new));
            try {
                $cost = $this->costing->preview($candidate)->summary();
            } catch (ValidationException $e) {
                $costErrors = $e->errors();
            }
        }

        return ['revision' => $trip->revision, 'columns' => array_map(fn ($index) => ['index' => $index, 'label' => ($header[$index] ?? 'Columna '.($index + 1)) ?: 'Columna '.($index + 1)], range(0, $columns - 1)),
            'rows' => $rows, 'count' => count($rows), 'invalid_count' => $errorCount, 'cost_errors' => $costErrors, 'can_apply' => ! $errorCount && ! $costErrors,
            'totals' => $cost ? array_intersect_key($cost, array_flip(['purchase', 'expenses', 'total', 'unassigned'])) : null];
    }

    public function fingerprint(string $source, array $settings): string
    {
        unset($settings['idempotency_key'], $settings['file'], $settings['source_text']);
        ksort($settings);
        ksort($settings['mapping']);

        return hash('sha256', $source."\n".json_encode($settings, JSON_THROW_ON_ERROR));
    }
}
