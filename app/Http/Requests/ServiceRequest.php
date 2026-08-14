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
            // Der Name ist der Schlüssel zur Farbe - zweimal derselbe Dienst
            // ergäbe zwei Farben für dieselbe Kachel.
            'name' => ['required', 'max:255', Rule::unique('services', 'name')->ignore($this->route('service'))],
            // Freie Farbe als Hex - der Farbwaehler liefert genau dieses Format.
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
