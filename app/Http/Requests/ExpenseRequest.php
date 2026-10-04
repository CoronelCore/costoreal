<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ExpenseRequest extends CostedLineRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'expense_category_id.exists' => 'Selecciona una categoría de tu espacio de trabajo.',
            'allocation_method.in' => 'Selecciona una regla de reparto admitida.',
            'target_product_id.required_if' => 'Selecciona el producto que recibe este gasto.',
            'target_product_id.exists' => 'Selecciona un producto de esta importación.',
            'comprobante.file' => 'Selecciona un archivo válido como comprobante.',
            'comprobante.mimes' => 'El comprobante debe ser PDF, JPEG, PNG o WebP.',
            'comprobante.max' => 'El comprobante no puede superar 10 MB.'];
    }

    public function rules(): array
    {
        return [...$this->metadataRules(), 'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $this->user()->id))],
            'concepto' => 'required|string|max:255', 'moneda' => 'required|in:CLP,'.$this->route('trip')->moneda_trabajo,
            'monto' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'allocation_method' => 'sometimes|in:value,units,weight,volume,direct', 'target_product_id' => ['nullable', 'required_if:allocation_method,direct', Rule::exists('products', 'id')->where('trip_id', $this->route('trip')->id)], 'comprobante' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240', 'eliminar_comprobante' => 'nullable|boolean'];
    }
}
