{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->domain,
                            $eintrag->providor,
                            $eintrag->host,
                            $eintrag->username,
                            'geheim' => [$eintrag, 'password'],
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'dyndns', id: {{ $eintrag->id }} })"
                        can="dyndns_update"
                    />

                
