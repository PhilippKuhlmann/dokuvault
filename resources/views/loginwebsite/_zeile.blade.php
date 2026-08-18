{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            'password' => $eintrag->password,
                            'url' => $eintrag->url,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'loginwebsite', id: {{ $eintrag->id }} })"
                        can="loginwebsite_update"
                    />

                
