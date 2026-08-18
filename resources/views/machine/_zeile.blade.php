{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    @php
                        // $adressen wurde hier nie gesetzt - die Zeile stammt aus
                        // den Listen mit Kartenansicht, wo sie oberhalb der
                        // Schleife steht. Die Seite antwortete deshalb mit 500,
                        // sobald ueberhaupt eine Maschine da war.
                        $adressen = $eintrag->relationLoaded('ipAddresses')
                            ? $eintrag->ipAddresses
                            : $eintrag->ipAddresses()->get();
                        $primaer = $adressen->first()?->address;
                    @endphp

                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $primaer,
                            'credentials' => $eintrag,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'machine', id: {{ $eintrag->id }} })"
                        can="machine_update"
                    />

                
