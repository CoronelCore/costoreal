<?php

namespace App\Http\Requests;

use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;

abstract class CostedLineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
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
        return ['expected_revision' => 'nullable|integer|min:1', 'rate_mode' => 'required|in:estimated,paid',
            'applied_rate' => ['nullable', 'required_if:rate_mode,paid', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,8', function ($attribute, $value, $fail) {
                if ($this->input('moneda') === 'CLP' && is_numeric($value) && ! BigDecimal::of((string) $value)->isEqualTo(1)) {
                    $fail('Una operación en CLP utiliza tasa 1.');
                }
            }],
            'rate_source' => 'required|string|max:255', 'rate_date' => 'nullable|required_if:rate_mode,paid|date_format:Y-m-d'];
    }
}
