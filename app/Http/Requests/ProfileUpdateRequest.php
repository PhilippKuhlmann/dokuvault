<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    /** Leere Auswahl heisst "der Browsersprache folgen", nicht Leerstring. */
    protected function prepareForValidation(): void
    {
        if ($this->input('locale') === '') {
            $this->merge(['locale' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'email' => ['email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'locale' => ['nullable', Rule::in(array_keys(config('custom.locales')))],
            // The email is where password resets go. Changing it from a
            // borrowed session would turn that session into the account -
            // so, like a new password, it needs the current one.
            'current_password' => [
                Rule::requiredIf(fn () => $this->emailChanges()),
                'nullable',
                'current_password',
            ],
        ];
    }

    public function emailChanges(): bool
    {
        return $this->has('email')
            && mb_strtolower(trim((string) $this->input('email'))) !== mb_strtolower((string) $this->user()->email);
    }
}
