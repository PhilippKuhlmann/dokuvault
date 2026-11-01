{{-- One row of the table. The frame sits in the generic list, the
     columns stay with the kind. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->domain,
                            $eintrag->providor,
                            $eintrag->host,
                            $eintrag->username,
                            'geheim' => [$eintrag, 'password'],
                        ]"

                        editAction="$dispatch('object-edit', { typ: 'dyndns', id: {{ $eintrag->id }} })"
                        can="dyndns_update"
                    />

                
