<?php

namespace App\Http\Requests;

use App\Support\ChileanNumber;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;

abstract class CostedLineRequest extends FormRequest
{
    public function messages(): array
    {
        return ['expected_revision.required' => 'Falta la revisión de origen. Revisa la importación antes de guardar.',
            'expected_revision.integer' => 'La revisión de origen debe ser un número entero.',
            'nombre.required' => 'Indica el nombre del producto.', 'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'concepto.required' => 'Indica el concepto del gasto.', 'concepto.max' => 'El concepto admite hasta 255 caracteres.',
            'cantidad.required' => 'Indica la cantidad de unidades.', 'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.', 'cantidad.max' => 'La cantidad no puede superar 1.000.000 unidades.',
            'costo_unitario.required' => 'Indica el costo unitario original.', 'costo_unitario.numeric' => 'El costo unitario debe ser numérico.',
            'costo_unitario.min' => 'El costo unitario no puede ser negativo.', 'costo_unitario.max' => 'El costo unitario supera el límite admitido.',
            'costo_unitario.decimal' => 'El costo original admite hasta dos decimales, por ejemplo 1.250,50.',
            'monto.required' => 'Indica el monto original del gasto.', 'monto.numeric' => 'El monto del gasto debe ser numérico.',
            'monto.min' => 'El monto no puede ser negativo.', 'monto.max' => 'El monto supera el límite admitido.',
            'monto.decimal' => 'El monto original admite hasta dos decimales, por ejemplo 450.000 o 12,50.',
            'moneda.in' => 'Usa CLP o la moneda de trabajo de esta importación.',
            'rate_mode.in' => 'Elige una conversión estimada o pagada.',
            'applied_rate.required_if' => 'Indica la tasa histórica del pago.', 'applied_rate.numeric' => 'La tasa histórica debe ser numérica.',
            'applied_rate.gt' => 'La tasa histórica debe ser mayor que cero.', 'applied_rate.max' => 'La tasa no puede superar 1.000.000 CLP.',
            'applied_rate.decimal' => 'La tasa admite hasta ocho decimales.',
            'rate_source.required' => 'Indica la fuente de la tasa del pago.', 'rate_source.max' => 'La fuente admite hasta 255 caracteres.',
            'rate_date.required_if' => 'Indica la fecha del pago.', 'rate_date.date_format' => 'Usa una fecha válida para el pago.',
            'weight.numeric' => 'El peso debe ser numérico.', 'weight.min' => 'El peso no puede ser negativo.',
            'weight.max' => 'El peso supera el límite admitido.', 'weight.decimal' => 'El peso admite hasta ocho decimales.',
            'volume.numeric' => 'El volumen debe ser numérico.', 'volume.min' => 'El volumen no puede ser negativo.',
            'volume.max' => 'El volumen supera el límite admitido.', 'volume.decimal' => 'El volumen admite hasta ocho decimales.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ChileanNumber::fields($this->all(), ['cantidad', 'costo_unitario', 'monto', 'applied_rate', 'weight', 'volume']));
        $record = $this->route('expense') ?? $this->route('product');
        $mode = $this->input('rate_mode', $record?->rate_mode ?? 'estimated');
        $this->merge(['rate_mode' => $mode]);
        if ($mode === 'estimated') {
            $this->merge(['rate_source' => 'trip-reference', 'applied_rate' => null, 'rate_date' => null]);
        } elseif ($mode === 'paid' && $record) {
            foreach (['applied_rate', 'rate_source', 'rate_date'] as $field) {
                if (! $this->exists($field)) {
                    $this->merge([$field => $record->{$field}]);
                }
            }
        }
    }

    protected function metadataRules(): array
    {
        return ['expected_revision' => 'required|integer|min:1', 'rate_mode' => 'required|in:estimated,paid',
            'applied_rate' => ['nullable', 'required_if:rate_mode,paid', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,8', function ($attribute, $value, $fail) {
                if ($this->input('moneda') === 'CLP' && is_numeric($value) && ! BigDecimal::of((string) $value)->isEqualTo(1)) {
                    $fail('Una operación en CLP utiliza tasa 1.');
                }
            }],
            'rate_source' => 'required|string|max:255', 'rate_date' => 'nullable|required_if:rate_mode,paid|date_format:Y-m-d'];
    }
}
