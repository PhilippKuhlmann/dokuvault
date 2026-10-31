{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            'geheim' => [$eintrag, 'password'],
                            $eintrag->description,
                            $eintrag->verwendetBei() ?: '—',

                        ]"

                        editAction="$dispatch('object-edit', { typ: 'logingeneral', id: {{ $eintrag->id }} })"
                        can="logingeneral_update"
                    />

                
