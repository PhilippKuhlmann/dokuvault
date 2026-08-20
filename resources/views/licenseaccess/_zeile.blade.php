{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->key,
                            'download' => $eintrag->file_path ?  route('licenseaccess.download', [$customer, $eintrag]) : NULL,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'licenseaccess', id: {{ $eintrag->id }} })"
                        can="licenseaccess_update"
                    />

                
