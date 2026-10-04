<?php

namespace App\Http\Requests;

class ProductRequest extends CostedLineRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return [...$this->metadataRules(), 'nombre' => 'required|string|max:255', 'moneda' => 'required|in:CLP,'.$this->route('trip')->moneda_trabajo,
            'cantidad' => 'required|integer|min:1|max:1000000', 'costo_unitario' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'weight' => 'nullable|numeric|min:0|max:999999999|decimal:0,8', 'volume' => 'nullable|numeric|min:0|max:999999999|decimal:0,8', 'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', 'eliminar_imagen' => 'nullable|boolean'];
    }
}
