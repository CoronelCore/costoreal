<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('company') ? $this->user()->can('update', $this->route('company')) : $this->user() !== null;
    }

    public function rules(): array
    {
        return ['nombre' => 'required|string|max:255', 'rut' => 'nullable|string|max:255',
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/', 'icono' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string', 'activa' => 'sometimes|boolean'];
    }

    public function messages(): array
    {
        return ['nombre.required' => 'Indica el nombre de la empresa.', 'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'rut.max' => 'El RUT admite hasta 255 caracteres.', 'color.required' => 'Elige un color para reconocer la empresa.',
            'color.size' => 'Usa un color hexadecimal de seis cifras, por ejemplo #2f615b.', 'color.regex' => 'Usa un color hexadecimal de seis cifras, por ejemplo #2f615b.',
            'icono.max' => 'Selecciona un icono válido.'];
    }
}
