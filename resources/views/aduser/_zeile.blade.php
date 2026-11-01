{{-- One row of the table. The frame sits in the generic list. --}}
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
                        editAction="$dispatch('object-edit', { typ: 'aduser', id: {{ $eintrag->id }} })"
                        can="aduser_update"
                    />

                
