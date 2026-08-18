{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->domain,
                            $eintrag->netbios,
                            'password' => $eintrag->dsrmpassword,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'addomain', id: {{ $eintrag->id }} })"
                        can="addomain_update"
                    />

                
