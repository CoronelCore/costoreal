<?php

namespace App\Http\Requests;

use App\Support\ChileanNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('trip') ? $this->user()->can('update', $this->route('trip')) : $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ChileanNumber::fields($this->all(), ['tipo_cambio']));
    }

    public function rules(): array
    {
        return ['expected_revision' => $this->route('trip') ? 'required|integer|min:1' : 'prohibited', 'company_id' => ['nullable', Rule::exists('companies', 'id')->where('user_id', $this->user()->id)],
            'nombre' => 'required|string|max:255', 'descripcion' => 'nullable|string|max:5000',
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', ...($this->filled('fecha_inicio') ? ['after_or_equal:fecha_inicio'] : [])],
            'moneda_trabajo' => 'required|in:USD,BRL,PEN,CNY,EUR', 'tipo_cambio' => 'required|numeric|gt:0|max:1000000|decimal:0,8',
            'estado' => ['sometimes', Rule::in([$this->route('trip')?->estado ?? 'activo'])]];
    }

    public function messages(): array
    {
        return ['estado.in' => 'Usa la acción de cierre o reapertura para cambiar el estado.',
            'nombre.required' => 'Indica un nombre para reconocer esta importación.', 'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'descripcion.max' => 'La descripción admite hasta 5.000 caracteres.', 'company_id.exists' => 'Elige una empresa de tu espacio de trabajo.',
            'fecha_inicio.date_format' => 'Indica una fecha de inicio válida.', 'fecha_fin.date_format' => 'Indica una fecha de fin válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            'moneda_trabajo.required' => 'Selecciona la moneda de trabajo.', 'moneda_trabajo.in' => 'Selecciona una moneda admitida.',
            'tipo_cambio.required' => 'Indica la tasa de referencia para convertir a CLP.', 'tipo_cambio.numeric' => 'Escribe la tasa como un número, por ejemplo 950 o 950,5.',
            'tipo_cambio.gt' => 'La tasa debe ser mayor que cero.', 'tipo_cambio.max' => 'La tasa no puede superar 1.000.000 CLP.',
            'tipo_cambio.decimal' => 'La tasa admite hasta ocho decimales.', 'expected_revision.required' => 'Falta la revisión de origen. Recarga antes de guardar.'];
    }
}
