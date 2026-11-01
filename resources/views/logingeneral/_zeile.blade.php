{{-- One row of the table. The frame sits in the generic list. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            'geheim' => [$eintrag, 'password'],
                            $eintrag->description,
                            $eintrag->verwendetBei() ?: '—',

                        ]"

                        editAction="$dispatch('object-edit', { typ: 'logingeneral', id: {{ $eintrag->id }} })"
                        can="logingeneral_update"
                    />

                
