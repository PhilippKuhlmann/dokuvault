{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->first_name . ' ' . $eintrag->last_name,
                            $eintrag->role,
                            $eintrag->phone,
                            $eintrag->mail,

                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'contactperson', id: {{ $eintrag->id }} })"
                        can="contactperson_update"

                    />

                
