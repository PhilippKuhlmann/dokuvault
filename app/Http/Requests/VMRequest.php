<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class VMRequest extends FormRequest
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
            // Required only without host and without cluster: if either is
            // set, the model takes the site from there (VM::booted) - the form
            // then hides the field and sends nothing at all.
            // Without both (a vServer at a provider) it stays the only
            // Ortsangabe.
            'site_id' => ['required_without_all:server_id,cluster_id', 'nullable', new BelongsToCustomer('sites')],
            // One or the other: in an HA cluster the VM moves between nodes,
            // and an additionally pinned host would be wrong after the first
            // migration. prohibits holds that even when both come from an old
            // form.
            'server_id' => ['nullable', 'prohibits:cluster_id', new BelongsToCustomer('servers')],
            'cluster_id' => ['nullable', new BelongsToCustomer('clusters')],
            'name' => 'required|max:255',
            'services' => 'max:255',
            // The column is NOT NULL - without a rule an empty choice ended
            // in a database error instead of a message.
            'operating_system_id' => 'required',
            'remoteID' => '',
            'remotePassword' => '',
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'server_id' => 'Host',
            'cluster_id' => 'Cluster',
            'name' => 'Name',
            'services' => 'Dienste',
            'cidr' => 'CIDR',
            'operating_system_id' => 'Betriebssystem',
            'remoteID' => Setting::remoteAccess()['id_label'],
            'remotePassword' => Setting::remoteAccess()['password_label'],
        ];
    }
}
