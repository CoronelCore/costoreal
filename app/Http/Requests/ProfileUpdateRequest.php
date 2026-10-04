<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Ingresa tu nombre.', 'name.max' => 'El nombre admite hasta :max caracteres.',
            'email.required' => 'Ingresa tu correo electrónico.', 'email.email' => 'Ingresa un correo electrónico válido.',
            'email.lowercase' => 'Escribe el correo en minúsculas.', 'email.unique' => 'Este correo ya está asociado a otra cuenta.',
            'email.max' => 'El correo admite hasta :max caracteres.'];
    }
}
