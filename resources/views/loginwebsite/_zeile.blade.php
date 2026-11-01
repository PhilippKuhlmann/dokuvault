{{-- One row of the table. The frame sits in the generic list, the
     columns stay with the kind. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            'geheim' => [$eintrag, 'password'],
                            'url' => $eintrag->url,
                        ]"

                        editAction="$dispatch('object-edit', { typ: 'loginwebsite', id: {{ $eintrag->id }} })"
                        can="loginwebsite_update"
                    />

                
