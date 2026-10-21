<?php

namespace App\Support;

use App\Models\PatchPanel;
use Illuminate\Validation\Rule;

/**
 * Labelling the ports of a patch panel - shared by the block in the browser
 * (App\Livewire\PatchPanelPorts) and the iOS app. The port rows themselves
 * come with the panel (PatchPanel::syncPorts), here they are only edited.
 */
class PatchPortEditing
{
    /** The columns of a port that can be edited. */
    public const COLUMNS = ['outlet', 'label', 'network_switch_id', 'switch_port', 'note'];

    /** Rules for one port, by column. */
    public function rules(int $customerId): array
    {
        return [
            // Schreibweise ist je Kunde verschieden ("EG 1.01", "A.12", "2.23") -
            // deshalb kein Format erzwingen, nur die Laenge begrenzen.
            'outlet' => 'nullable|string|max:50',
            'label' => 'nullable|string|max:255',
            'switch_port' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:255',
            // Kundengebunden: der Switch eines fremden Kunden wird abgelehnt.
            'network_switch_id' => [
                'nullable',
                Rule::exists('network_switches', 'id')
                    ->where('customer_id', $customerId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Write the given values: port id => [column => value]. Only ports of
     * this panel - a foreign port id is skipped, not written. Columns left
     * out stay as they are; "" becomes null.
     */
    public function save(PatchPanel $panel, array $werte): void
    {
        foreach ($panel->ports()->get() as $port) {
            if (! isset($werte[$port->id])) {
                continue;
            }

            $port->update(array_map(
                fn ($wert) => $wert === '' ? null : $wert,
                array_intersect_key($werte[$port->id], array_flip(self::COLUMNS))
            ));
        }
    }

    /** Eine Zeile leeren, ohne alle Felder einzeln loeschen zu muessen. */
    public function clear(PatchPanel $panel, int $portId): void
    {
        $panel->ports()->whereKey($portId)->update(array_fill_keys(self::COLUMNS, null));
    }
}
