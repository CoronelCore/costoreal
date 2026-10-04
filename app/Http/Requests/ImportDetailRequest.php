<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('trip')) ?? false;
    }

    public function rules(): array
    {
        return ['section' => 'nullable|in:resumen,productos,gastos,precio-venta,revision,historial,documentos,escenarios,config',
            'scenario_page' => 'nullable|integer|min:1', 'history_page' => 'nullable|integer|min:1'];
    }
}
