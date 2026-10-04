<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('category') ? $this->user()->can('update', $this->route('category')) : $this->user() !== null;
    }

    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'max:255', Rule::unique('expense_categories')->where('user_id', $this->user()->id)->ignore($this->route('category')?->id)],
            'icono' => 'required|string|max:50', 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']];
    }

    public function messages(): array
    {
        return ['nombre.required' => 'Indica el nombre de la categoría.', 'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'nombre.unique' => 'Ya tienes una categoría con ese nombre.', 'icono.required' => 'Elige un icono para la categoría.', 'icono.max' => 'Selecciona un icono válido.',
            'color.required' => 'Elige un color para la categoría.', 'color.regex' => 'Usa un color hexadecimal de seis cifras, por ejemplo #2f615b.'];
    }
}
