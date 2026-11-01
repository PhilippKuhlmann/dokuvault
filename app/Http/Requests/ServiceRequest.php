<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // The name is the key to the colour - the same service twice
            // ergäbe zwei Farben für dieselbe Kachel.
            'name' => ['required', 'max:255', Rule::unique('services', 'name')->ignore($this->route('service'))],
            // Explains the name where it is chosen - "UMA" otherwise means
            // something only to those who already know it.
            'description' => ['nullable', 'max:255'],
            // A free colour as hex - the colour picker returns exactly this format.
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
