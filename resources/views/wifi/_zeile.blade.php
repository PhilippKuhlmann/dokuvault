{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->ssid,
                            $eintrag->network ? ($eintrag->network->vlanId . ' - ' . $eintrag->network->description) : '—',
                            $eintrag->encryption,
                            'password' => $eintrag->password,
                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'wifi', id: {{ $eintrag->id }} })"
                        can="wifi_update"

                    />

                
