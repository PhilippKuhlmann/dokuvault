<?php

namespace App\Support;

use App\Models\IpRange;
use App\Models\Network;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reserved ranges of a network: the rules for creating and deleting one.
 * Shared by the block in the browser (Livewire\IpBereiche) and the iOS app
 * (Mobile\NetworkController).
 */
class IpRangeEditing
{
    public function rules(): array
    {
        return [
            'from_ip' => ['required', 'ipv4'],
            'to_ip' => ['required', 'ipv4'],
            'label' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributeNames(): array
    {
        return [
            'from_ip' => __('Von'),
            'to_ip' => __('Bis'),
            'label' => __('Wofür'),
            'note' => __('Notiz'),
        ];
    }

    /**
     * Create a range in $netz from validated data. Throws a ValidationException
     * if it is reversed, outside the network or overlaps another range.
     */
    public function create(Network $netz, array $daten): IpRange
    {
        $von = IpRange::alsLong($daten['from_ip']);
        $bis = IpRange::alsLong($daten['to_ip']);

        // The order first: without it every further message would be noise.
        if ($bis < $von) {
            throw ValidationException::withMessages([
                'to_ip' => __('Die Endadresse liegt vor der Anfangsadresse.'),
            ]);
        }

        // Inside the network, not next to it. Checked through the same function
        // the application otherwise uses to decide whether an address belongs to a network.
        foreach (['from_ip', 'to_ip'] as $feld) {
            if (! $netz->containsUrl($daten[$feld])) {
                throw ValidationException::withMessages([
                    $feld => __('Diese Adresse liegt nicht in :netz.', ['netz' => $netz->label() ?: $netz->network]),
                ]);
            }
        }

        // Two reservations for the same address would be a contradiction in
        // the documentation, not a state worth modelling.
        $ueberschneidung = $this->ranges($netz)->first(
            fn (IpRange $b) => $b->vonLong() !== null && $b->bisLong() !== null
                && $von <= $b->bisLong() && $bis >= $b->vonLong()
        );

        if ($ueberschneidung) {
            throw ValidationException::withMessages([
                'from_ip' => __('Überschneidet sich mit „:label" (:von – :bis).', [
                    'label' => $ueberschneidung->label,
                    'von' => $ueberschneidung->from_ip,
                    'bis' => $ueberschneidung->to_ip,
                ]),
            ]);
        }

        return IpRange::create([
            'customer_id' => $netz->customer_id,
            'network_id' => $netz->id,
            'from_ip' => $daten['from_ip'],
            'to_ip' => $daten['to_ip'],
            'label' => trim($daten['label']),
            'note' => trim((string) ($daten['note'] ?? '')) ?: null,
        ]);
    }

    /** Only a range of this network - a foreign id is ignored. */
    public function delete(Network $netz, int $id): void
    {
        $this->ranges($netz)->firstWhere('id', $id)?->delete();
    }

    /**
     * The ranges of a network, coloured and sorted as in the plan - the list
     * is its legend, and two orders would drift apart.
     */
    public function ranges(Network $netz): Collection
    {
        return IpRange::eingefaerbt(
            IpRange::where('network_id', $netz->id)
                ->where('customer_id', $netz->customer_id)
                ->get()
        );
    }
}
