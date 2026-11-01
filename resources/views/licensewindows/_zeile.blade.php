{{-- One row of the table. The frame sits in the generic list. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->operatingSystem?->name ?? '—',
                            $eintrag->key,
                            'download' => $eintrag->file_path ?  route('licensewindows.download', [$customer, $eintrag]) : NULL,

                        ]"

                        editAction="$dispatch('object-edit', { typ: 'licensewindows', id: {{ $eintrag->id }} })"
                        can="licensewindows_update"
                    />

                
