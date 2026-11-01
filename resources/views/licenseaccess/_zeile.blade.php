{{-- One row of the table. The frame sits in the generic list. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->key,
                            'download' => $eintrag->file_path ?  route('licenseaccess.download', [$customer, $eintrag]) : NULL,
                        ]"

                        editAction="$dispatch('object-edit', { typ: 'licenseaccess', id: {{ $eintrag->id }} })"
                        can="licenseaccess_update"
                    />

                
