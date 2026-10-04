<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('trip'));
    }

    public function rules(): array
    {
        return ['expected_revision' => 'required|integer|min:1', 'source_text' => 'required_without:file|nullable|string|max:2097152',
            'file' => 'required_without:source_text|nullable|file|mimes:csv,txt|max:2048', 'delimiter' => ['required', Rule::in([',', ';', 'tab'])],
            'has_header' => 'required|boolean', 'default_currency' => 'required|in:CLP,'.$this->route('trip')->moneda_trabajo,
            'mapping' => 'required|array:nombre,cantidad,moneda,costo_unitario,weight,volume',
            'mapping.nombre' => 'required|integer|between:0,31', 'mapping.cantidad' => 'required|integer|between:0,31',
            'mapping.costo_unitario' => 'required|integer|between:0,31', 'mapping.moneda' => 'nullable|integer|between:0,31',
            'mapping.weight' => 'nullable|integer|between:0,31', 'mapping.volume' => 'nullable|integer|between:0,31',
            'idempotency_key' => [$this->routeIs('trips.products.import.apply') ? 'required' : 'nullable', 'uuid']];
    }

    public function settings(): array
    {
        $settings = $this->safe()->except(['source_text', 'file', 'idempotency_key']);
        $settings['expected_revision'] = (int) $settings['expected_revision'];
        $settings['has_header'] = $this->boolean('has_header');
        foreach (['nombre', 'cantidad', 'moneda', 'costo_unitario', 'weight', 'volume'] as $field) {
            $value = $settings['mapping'][$field] ?? null;
            $settings['mapping'][$field] = $value === null || $value === '' ? null : (int) $value;
        }

        return $settings;
    }

    public function source(): string
    {
        return $this->hasFile('file') ? file_get_contents($this->file('file')->getRealPath()) : (string) $this->source_text;
    }
}
