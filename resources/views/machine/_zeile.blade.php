{{-- One row of the table. The frame sits in the generic list, the
     columns stay with the kind. --}}
                    @php
                        // $adressen wurde hier nie gesetzt - die Zeile stammt aus
                        // den Listen mit Kartenansicht, wo sie oberhalb der
                        // Schleife steht. Die Seite antwortete deshalb mit 500,
                        // sobald ueberhaupt eine Maschine da war.
                        $adressen = $eintrag->relationLoaded('ipAddresses')
                            ? $eintrag->ipAddresses
                            : $eintrag->ipAddresses()->get();
                        $primaer = $adressen->first()?->label();
                    @endphp

                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $primaer,
                            'credentials' => $eintrag,
                        ]"

                        editAction="$dispatch('object-edit', { typ: 'machine', id: {{ $eintrag->id }} })"
                        can="machine_update"
                    />

                
