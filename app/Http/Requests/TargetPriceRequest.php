<?php

namespace App\Http\Requests;

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
    }

    public function rules(): array
    {
        return ['expected_revision' => 'required|integer|min:1', 'product_ids' => 'required|array|min:1|max:1000',
            'product_ids.*' => 'required|integer|min:1|distinct', 'method' => 'required|in:margin,markup',
            'target' => 'required|numeric|min:0|max:1000|decimal:0,2', 'commission' => 'required|numeric|min:0|lt:100|decimal:0,2',
            'quantum' => 'required|integer|in:1,10,100,1000'];
    }
}
