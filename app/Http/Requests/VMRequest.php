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
            // Nur ohne Host und ohne Cluster Pflicht: Steht eines von beiden,
            // uebernimmt das Model den Standort von dort (VM::booted) - das
            // Formular blendet das Feld dann aus und schickt gar nichts mit.
            // Ohne beides (vServer beim Anbieter) bleibt es die einzige
            // Ortsangabe.
            'site_id' => ['required_without_all:server_id,cluster_id', 'nullable', new BelongsToCustomer('sites')],
            // Entweder oder: In einem HA-Cluster wandert die VM zwischen den
            // Knoten, ein zusaetzlich gepinnter Host waere nach der ersten
            // Migration falsch. prohibits haelt das auch dann ein, wenn beides
            // aus einem alten Formular kommt.
            'server_id' => ['nullable', 'prohibits:cluster_id', new BelongsToCustomer('servers')],
            'cluster_id' => ['nullable', new BelongsToCustomer('clusters')],
            'name' => 'required|max:255',
            'services' => 'max:255',
            // Die Spalte ist NOT NULL - ohne Regel endete eine leere
            // Auswahl in einem Datenbankfehler statt in einer Meldung.
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
            'remoteID' => Setting::fernwartung()['id_label'],
            'remotePassword' => Setting::fernwartung()['password_label'],
        ];
    }
}
