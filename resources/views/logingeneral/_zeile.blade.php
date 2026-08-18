{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            'password' => $eintrag->password,
                            $eintrag->description,
                            $eintrag->verwendetBei() ?: '—',

                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'logingeneral', id: {{ $eintrag->id }} })"
                        can="logingeneral_update"
                    />

                
