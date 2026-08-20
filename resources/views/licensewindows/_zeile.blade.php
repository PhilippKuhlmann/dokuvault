{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->operatingSystem?->name ?? '—',
                            $eintrag->key,
                            'download' => $eintrag->file_path ?  route('licensewindows.download', [$customer, $eintrag]) : NULL,

                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'licensewindows', id: {{ $eintrag->id }} })"
                        can="licensewindows_update"
                    />

                
