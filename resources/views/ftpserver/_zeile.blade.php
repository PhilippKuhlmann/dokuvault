{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->host,
                            $eintrag->username,
                            'password' => $eintrag->password,
                            $eintrag->description,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'ftpserver', id: {{ $eintrag->id }} })"
                        can="ftpserver_update"
                    />

                
