{{-- One row of the table. The frame sits in the generic list. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->street . ' ' . $eintrag->house_number,
                            $eintrag->zip . ' ' . $eintrag->city,

                        ]"

                        editAction="$dispatch('object-edit', { typ: 'site', id: {{ $eintrag->id }} })"
                        can="site_update"

                    />

                
