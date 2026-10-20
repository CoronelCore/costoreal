<?php

namespace App\Http\Requests;

use App\Support\ChileanNumber;
use Illuminate\Foundation\Http\FormRequest;

class TargetPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('trips.pricing.apply') ? 'update' : 'view', $this->route('trip'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['commission' => $this->input('commission', '0'), 'quantum' => $this->input('quantum', 1)]);
        $this->merge(ChileanNumber::fields($this->all(), ['target', 'commission']));
    }

    public function messages(): array
    {
        return ['product_ids.required' => 'Elige al menos un producto.', 'product_ids.min' => 'Elige al menos un producto.',
            'method.in' => 'Elige margen sobre venta o recargo sobre costo.',
            'target.required' => 'Indica el porcentaje objetivo.', 'target.numeric' => 'Escribe el porcentaje como un número, por ejemplo 20 o 12,5.',
            'target.max' => 'El porcentaje no puede superar 1.000 %.', 'target.decimal' => 'El porcentaje admite hasta dos decimales.',
            'commission.numeric' => 'Escribe la comisión como un número, por ejemplo 3 o 3,5.', 'commission.lt' => 'La comisión debe ser menor que 100 %.',
            'commission.decimal' => 'La comisión admite hasta dos decimales.', 'quantum.in' => 'Elige un redondeo de 1, 10, 100 o 1.000 pesos.'];
    }

    public function rules(): array
    {
        return ['expected_revision' => 'required|integer|min:1', 'product_ids' => 'required|array|min:1|max:1000',
            'product_ids.*' => 'required|integer|min:1|distinct', 'method' => 'required|in:margin,markup',
            'target' => 'required|numeric|min:0|max:1000|decimal:0,2', 'commission' => 'required|numeric|min:0|lt:100|decimal:0,2',
            'quantum' => 'required|integer|in:1,10,100,1000'];
    }
}
