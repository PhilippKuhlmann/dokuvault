{{-- One row of the table. The frame sits in the generic list, the
     columns stay with the kind. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->first_name . ' ' . $eintrag->last_name,
                            $eintrag->role,
                            $eintrag->phone,
                            $eintrag->mail,

                        ]"

                        editAction="$dispatch('object-edit', { typ: 'contactperson', id: {{ $eintrag->id }} })"
                        can="contactperson_update"

                    />

                
