<?php

namespace App\Support;

use App\Models\IpAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * IP addresses of a device - shared by the block in the browser
 * (App\Livewire\DeviceIpAddresses) and the iOS app. Permissions on the
 * device stay with the caller.
 */
class IpAssignment
{
    public function rules(int $customerId, bool $dhcp): array
    {
        return [
            // Eine Adresse gibt es beim selben Kunden nur einmal. Vorher liess
            // sich dieselbe IP zweimal am selben Geraet und zusaetzlich an
            // einem zweiten eintragen - danach stand in der Doku, sie gehoere
            // zu beiden, und der IP-Plan zeigte sie doppelt als belegt.
            // Bei DHCP keine Adresse: Welche das Geraet gerade hat, ist
            // morgen eine andere. Dafuer dann das Netz - ohne es saehe die
            // Zuordnung so aus: "haengt irgendwo per DHCP", und das hilft
            // niemandem.
            'address' => $dhcp
                ? ['nullable', 'prohibited']
                : ['required', 'ip', $this->notYetTaken($customerId)],
            'network_id' => [
                $dhcp ? 'required' : 'nullable',
                Rule::exists('networks', 'id')->where('customer_id', $customerId),
            ],
            'label' => ['nullable', 'max:255'],
            'dhcp' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['address.unique' => __('Diese Adresse ist bei diesem Kunden schon vergeben.')];
    }

    public function attributeNames(): array
    {
        return ['address' => __('Adresse'), 'network_id' => __('Netz'), 'label' => __('Bezeichnung'), 'dhcp' => __('DHCP')];
    }

    public function add(Model $device, array $daten, bool $dhcp): IpAddress
    {
        return $device->ipAddresses()->create([
            'customer_id' => $device->customer_id,
            'network_id' => ($daten['network_id'] ?? null) ?: null,
            'address' => $dhcp ? null : $daten['address'],
            'label' => ($daten['label'] ?? null) ?: null,
            'dhcp' => $dhcp,
        ]);
    }

    public function remove(Model $device, int $id): void
    {
        $device->ipAddresses()->whereKey($id)->delete();
    }

    /**
     * Die Adresse darf beim Kunden noch nicht vergeben sein.
     *
     * Geloeschte Eintraege zaehlen nicht mit: Was im Papierkorb liegt, belegt
     * keine Adresse - sonst blieben Adressen nach dem Aufraeumen gesperrt.
     */
    private function notYetTaken(int $customerId)
    {
        return Rule::unique('ip_addresses', 'address')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at');
    }
}
