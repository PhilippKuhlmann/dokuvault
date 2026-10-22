<?php

namespace App\Support;

use App\Models\Network;
use Illuminate\Validation\Rule;

/**
 * VLANs: the rules for creating and editing one. Shared by the modal in the
 * browser (Livewire\NetworkQuickCreate) and the iOS app
 * (Mobile\NetworkController).
 */
class NetworkEditing
{
    public const COLUMNS = ['site_id', 'description', 'vlanId', 'network', 'subnetmask', 'cidr',
        'gateway', 'dns1', 'dns2', 'dhcpStart', 'dhcpEnd'];

    /**
     * @param  bool  $mitStandort  false when the site is given by the caller
     *                             (the device the modal hangs on)
     */
    public function rules(int $customerId, bool $mitStandort = true): array
    {
        $regeln = [
            'description' => ['required', 'max:255'],
            'vlanId' => ['nullable', 'integer', 'min:1', 'max:4094'],
            'network' => ['required', 'ipv4'],
            'subnetmask' => ['required', 'ipv4'],
            'cidr' => ['nullable', 'integer', 'min:0', 'max:32'],
            'gateway' => ['nullable', 'ipv4'],
            'dns1' => ['nullable', 'ipv4'],
            'dns2' => ['nullable', 'ipv4'],
            'dhcpStart' => ['nullable', 'ipv4'],
            'dhcpEnd' => ['nullable', 'ipv4'],
        ];

        // Ein Standort dieses Kunden, sonst haengt das Netz an einem fremden.
        if ($mitStandort) {
            $regeln['site_id'] = ['required', Rule::exists('sites', 'id')
                ->where('customer_id', $customerId)->whereNull('deleted_at')];
        }

        return $regeln;
    }

    /** Die Beschriftungen fuer die Meldungen - "Bitte Bezeichnung angeben.". */
    public function attributeNames(): array
    {
        return [
            'site_id' => __('Standort'),
            'description' => __('Bezeichnung'),
            'vlanId' => __('VLAN-ID'),
            'network' => __('Netz'),
            'subnetmask' => __('Subnetzmaske'),
            'cidr' => __('CIDR'),
            'gateway' => __('Gateway'),
            'dns1' => __('DNS 1'),
            'dns2' => __('DNS 2'),
            'dhcpStart' => __('DHCP-Start'),
            'dhcpEnd' => __('DHCP-Ende'),
        ];
    }

    /** Validated data as columns: empty optional fields become null. */
    public function values(array $daten, int $siteId): array
    {
        return [
            'site_id' => $siteId,
            'description' => $daten['description'],
            'vlanId' => ($daten['vlanId'] ?? null) ?: null,
            'network' => $daten['network'],
            'subnetmask' => $daten['subnetmask'],
            'cidr' => ($daten['cidr'] ?? null) ?: null,
            'gateway' => ($daten['gateway'] ?? null) ?: null,
            'dns1' => ($daten['dns1'] ?? null) ?: null,
            'dns2' => ($daten['dns2'] ?? null) ?: null,
            'dhcpStart' => ($daten['dhcpStart'] ?? null) ?: null,
            'dhcpEnd' => ($daten['dhcpEnd'] ?? null) ?: null,
        ];
    }

    /**
     * Mask and CIDR say the same thing - the browser fills one from the other
     * while typing; for a request that sent only one, this does it.
     */
    public function completeMask(array $eingabe): array
    {
        $maske = trim((string) ($eingabe['subnetmask'] ?? ''));
        $cidr = $eingabe['cidr'] ?? null;

        if ($maske === '' && ($ausCidr = Network::maskeAusCidr($cidr)) !== null) {
            $eingabe['subnetmask'] = $ausCidr;
        } elseif (($cidr === null || $cidr === '') && ($ausMaske = Network::cidrAusMaske($maske)) !== null) {
            $eingabe['cidr'] = $ausMaske;
        }

        return $eingabe;
    }
}
