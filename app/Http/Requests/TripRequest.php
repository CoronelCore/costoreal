<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('trip') ? $this->user()->can('update', $this->route('trip')) : $this->user() !== null;
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
        return ['estado.in' => 'Usa la acción de cierre o reapertura para cambiar el estado.'];
    }
}
