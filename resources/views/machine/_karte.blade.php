{{-- Ein Eintrag in der Liste. Als eigenes Teilstueck, damit die
     generische Liste (App\Livewire\ObjektListe) es einbinden kann -
     die Karte bleibt beim Typ, weil gerade ihre Unterschiede die
     Information tragen. --}}
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

                
