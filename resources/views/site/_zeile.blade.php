{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->street . ' ' . $eintrag->house_number,
                            $eintrag->zip . ' ' . $eintrag->city,

                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'site', id: {{ $eintrag->id }} })"
                        can="site_update"

                    />

                
