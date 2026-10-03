{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->description,
                            'liste:Mitglieder' => $eintrag->users->map(fn ($u) => $u->username ?: trim($u->firstName.' '.$u->lastName))->all(),
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'adgroup', id: {{ $eintrag->id }} })"
                        can="adgroup_update"
                    />

                
