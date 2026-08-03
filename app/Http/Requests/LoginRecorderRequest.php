<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRecorderRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recorder_id' => ['required', new BelongsToCustomer('recorders')],
            'username' => 'required|max:255',
            'password' => 'required|max:255',
            'hidden' => '',
        ];
    }

    public function attributes()
    {
        return [
            'recorder_id' => 'Recorder',
            'username' => 'Benutzername',
            'password' => 'Passwort',
        ];
    }
}
