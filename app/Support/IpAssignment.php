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
            // An address exists only once per customer. Before, the same IP
            // could be entered twice on the same device and on a second one as
            // well - after which the documentation said it belonged to both,
            // and the IP plan showed it twice as taken.
            // With DHCP no address: whichever the device holds now is a
            // different one tomorrow. The network instead - without it the
            // assignment would read "hangs somewhere by DHCP", and that helps
            // nobody.
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
     * The address must not yet be in use at this customer.
     *
     * Deleted entries do not count: what lies in the trash takes no address -
     * otherwise addresses would stay blocked after a cleanup.
     */
    private function notYetTaken(int $customerId)
    {
        return Rule::unique('ip_addresses', 'address')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at');
    }
}
