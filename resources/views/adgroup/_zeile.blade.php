{{-- One row of the table. The frame sits in the generic list, the
     columns stay with the kind. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->description,
                            'liste:Mitglieder' => $eintrag->users->map(fn ($u) => $u->username ?: trim($u->firstName.' '.$u->lastName))->all(),
                        ]"

                        editAction="$dispatch('object-edit', { typ: 'adgroup', id: {{ $eintrag->id }} })"
                        can="adgroup_update"
                    />

                
