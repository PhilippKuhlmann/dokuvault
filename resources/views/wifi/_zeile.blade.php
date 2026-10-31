{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->ssid,
                            $eintrag->network ? ($eintrag->network->vlanId . ' - ' . $eintrag->network->description) : '—',
                            $eintrag->encryption,
                            'geheim' => [$eintrag, 'password'],
                        ]"

                        editAction="$dispatch('object-edit', { typ: 'wifi', id: {{ $eintrag->id }} })"
                        can="wifi_update"

                    />

                
