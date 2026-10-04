<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('trip'));
    }

    public function rules(): array
    {
        return ['expected_revision' => 'required|integer|min:1', 'name' => [$this->routeIs('trips.scenarios.store') ? 'required' : 'nullable', 'string', 'max:255'],
            'idempotency_key' => [$this->routeIs('trips.scenarios.store') ? 'required' : 'nullable', 'uuid'],
            'reference_rate' => 'nullable|numeric|gt:0|max:1000000|decimal:0,8', 'expense_variation_percent' => 'nullable|numeric|min:-100|max:1000|decimal:0,2',
            'products' => 'sometimes|array|max:1000', 'products.*' => 'required|array:id,cantidad,costo_unitario,weight,volume,precio_venta',
            'products.*.id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('trip_id', $this->route('trip')->id)],
            'products.*.cantidad' => 'sometimes|required|integer|min:1|max:1000000', 'products.*.costo_unitario' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'products.*.weight' => 'sometimes|nullable|numeric|min:0|max:999999999|decimal:0,8', 'products.*.volume' => 'sometimes|nullable|numeric|min:0|max:999999999|decimal:0,8',
            'products.*.precio_venta' => 'sometimes|nullable|numeric|gt:0|max:9999999999.99|decimal:0,2',
            'expenses' => 'sometimes|array|max:500', 'expenses.*' => 'required|array:id,monto,allocation_method,target_product_id',
            'expenses.*.id' => ['required', 'integer', 'distinct', Rule::exists('expenses', 'id')->where('trip_id', $this->route('trip')->id)],
            'expenses.*.monto' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2', 'expenses.*.allocation_method' => 'sometimes|required|in:value,units,weight,volume,direct',
            'expenses.*.target_product_id' => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')->where('trip_id', $this->route('trip')->id)],
            'pricing' => 'nullable|array:method,target,commission,quantum,product_ids', 'pricing.method' => 'required_with:pricing|in:margin,markup',
            'pricing.target' => 'required_with:pricing|numeric|min:0|max:1000|decimal:0,2', 'pricing.commission' => 'required_with:pricing|numeric|min:0|lt:100|decimal:0,2',
            'pricing.quantum' => 'required_with:pricing|integer|in:1,10,100,1000', 'pricing.product_ids' => 'required_with:pricing|array|min:1|max:1000',
            'pricing.product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('trip_id', $this->route('trip')->id)]];
    }
}
