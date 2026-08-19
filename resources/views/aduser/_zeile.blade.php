{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->firstName,
                            $eintrag->lastName,
                            $eintrag->username,
                            $eintrag->email,
                            $eintrag->enabled === null ? '—' : ($eintrag->enabled ? 'Aktiv' : 'Deaktiviert'),
                            'password' => $eintrag->password,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'aduser', id: {{ $eintrag->id }} })"
                        can="aduser_update"
                    />

                
