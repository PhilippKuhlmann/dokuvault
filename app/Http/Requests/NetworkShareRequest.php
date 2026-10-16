<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class NetworkShareRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|max:255',
            'drive_letter' => ['nullable', 'regex:/^[A-Za-z]:?$/'],
            'path' => 'required|max:255',
            'ad_group_read_id' => ['nullable', new BelongsToCustomer('ad_groups')],
            'ad_group_write_id' => ['nullable', new BelongsToCustomer('ad_groups')],
            'sensitive' => 'nullable|boolean',
            'description' => 'nullable|max:2000',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'drive_letter' => 'Laufwerksbuchstabe',
            'path' => 'Pfad',
            'ad_group_read_id' => 'AD-Gruppe Lesen',
            'ad_group_write_id' => 'AD-Gruppe Schreiben',
            'sensitive' => 'Sensibel',
            'description' => 'Beschreibung',
        ];
    }
}
