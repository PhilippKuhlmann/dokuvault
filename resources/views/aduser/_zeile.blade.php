{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->firstName,
                            $eintrag->lastName,
                            $eintrag->username,
                            $eintrag->email,
                            'liste:Gruppen' => $eintrag->groups->pluck('name')->all(),
                            'status' => $eintrag->enabled,
                            'geheim' => [$eintrag, 'password'],
                        ]"

                        :inaktiv="$eintrag->enabled === false"
                        editAction="$dispatch('objekt-bearbeiten', { typ: 'aduser', id: {{ $eintrag->id }} })"
                        can="aduser_update"
                    />

                
