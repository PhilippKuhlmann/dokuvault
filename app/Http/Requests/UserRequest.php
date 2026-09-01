<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'username' => 'required',
            // Dieselbe Latte wie im eigenen Profil (PasswordController). Vorher
            // reichten hier sechs Zeichen - ein Kennwort, das sich trotz der
            // Sperre in LoginRequest noch durchprobieren laesst.
            'password' => $this->isMethod('post')
                ? ['required', Password::defaults()]
                : ['nullable', Password::defaults()],
            'email' => 'nullable',
            'role_id' => 'required',
            'customer_id' => 'required_if:role_id,98,99',
        ];
    }
}
