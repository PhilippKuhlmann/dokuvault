<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|max:255',
            'customer_number' => '',
            // No 'slug': it is made once from the name when the customer is
            // created (Customer::creating) and is the storage folder and URL
            // segment of all its files - not something to set from a form.
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'customer_number' => 'Kundennummer',
        ];
    }
}
