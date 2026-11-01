<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            // six characters were enough here - a password that could be
            // Sperre in LoginRequest noch durchprobieren laesst.
            //
            // When inviting there is none here: the user sets it themselves,
            // and a password the administrator knows and passes on by word of
            // mouth would be the weaker method anyway.
            'password' => $this->isMethod('post') && ! $this->boolean('einladen')
                ? ['required', Password::defaults()]
                : ['nullable', Password::defaults()],
            'einladen' => 'boolean',
            // Without an address the invitation can go nowhere.
            'email' => [$this->boolean('einladen') ? 'required' : 'nullable', 'email'],
            'role_id' => ['required', Rule::exists('roles', 'id')->whereNull('deleted_at')],
            // Frueher stand hier required_if:role_id,98,99 - feste Rollennummern
            // from a time before the role admin area. Such roles exist in no
            // installation, so the rule was never true. And it cannot come
            // back either: whether somebody is a customer user hangs on
            // exactly this field, not on their role - a role that could force
            // it does not exist.
            //
            // What was missing here and really counts: that the number belongs
            // to a customer who exists and is not in the trash.
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'two_factor_required' => 'boolean',
            'deactivated' => 'boolean',
        ];
    }

    /**
     * A box left unticked is not sent by the browser at all.
     * Without this line the requirement could be set but never removed
     * again - the missing value would simply mean "unchanged".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'two_factor_required' => $this->boolean('two_factor_required'),
            'deactivated' => $this->boolean('deactivated'),
            'einladen' => $this->boolean('einladen'),
        ]);
    }
}
