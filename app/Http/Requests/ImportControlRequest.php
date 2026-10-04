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
}
