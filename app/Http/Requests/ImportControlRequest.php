<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['q' => 'nullable|string|max:150', 'state' => 'nullable|in:activo,finalizado,archivado',
            'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => 'nullable|in:latest,cost,name', 'page' => 'nullable|integer|min:1'];
    }

    public function messages(): array
    {
        return ['q.string' => 'Escribe un nombre para buscar.', 'q.max' => 'La búsqueda admite hasta 150 caracteres.',
            'state.in' => 'Selecciona un estado de la lista.', 'sort.in' => 'Selecciona un orden de la lista.',
            'from.date_format' => 'Usa una fecha de inicio válida.', 'to.date_format' => 'Usa una fecha de fin válida.',
            'to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'page.integer' => 'Selecciona una página válida.', 'page.min' => 'La primera página es la número uno.'];
    }
}
