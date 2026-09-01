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
            // reichten hier sechs Zeichen - ein Kennwort, das sich trotz der
            // Sperre in LoginRequest noch durchprobieren laesst.
            //
            // Beim Einladen gibt es hier keins: Der Benutzer vergibt es sich
            // selbst, und ein Kennwort, das der Administrator kennt und per
            // Zuruf weitergibt, waere ohnehin das schwaechere Verfahren.
            'password' => $this->isMethod('post') && ! $this->boolean('einladen')
                ? ['required', Password::defaults()]
                : ['nullable', Password::defaults()],
            'einladen' => 'boolean',
            // Ohne Adresse kann die Einladung nirgendwohin.
            'email' => [$this->boolean('einladen') ? 'required' : 'nullable', 'email'],
            'role_id' => ['required', Rule::exists('roles', 'id')->whereNull('deleted_at')],
            // Frueher stand hier required_if:role_id,98,99 - feste Rollennummern
            // aus einer Zeit vor dem Rollen-Adminbereich. Solche Rollen gibt es
            // in keiner Installation, die Regel war also nie wahr. Und sie kann
            // auch nicht wiederkommen: ob jemand Kundennutzer ist, haengt an
            // genau diesem Feld, nicht an seiner Rolle - eine Rolle, die es
            // erzwingen koennte, gibt es nicht.
            //
            // Was hier fehlte und wirklich zaehlt: dass die Nummer zu einem
            // Kunden gehoert, den es gibt und der nicht im Papierkorb liegt.
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'two_factor_required' => 'boolean',
        ];
    }

    /**
     * Ein nicht angehakter Kasten wird vom Browser gar nicht mitgeschickt.
     * Ohne diese Zeile liesse sich die Pflicht setzen, aber nie wieder
     * loeschen - der fehlende Wert waere schlicht "nicht geaendert".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'two_factor_required' => $this->boolean('two_factor_required'),
            'einladen' => $this->boolean('einladen'),
        ]);
    }
}
