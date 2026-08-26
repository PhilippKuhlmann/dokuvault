{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            $eintrag->verfahrenName(),
                            $eintrag->description,
                            'fingerprint' => $eintrag->fingerprint,
                            $eintrag->verwendetBei() ?: '—',

                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'sshkey', id: {{ $eintrag->id }} })"
                        can="sshkey_update"
                    />
