{{-- One row of the table. The frame sits in the generic list. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->name,
                            $eintrag->username,
                            $eintrag->algorithmName(),
                            $eintrag->description,
                            'fingerprint' => $eintrag->fingerprint,
                            $eintrag->verwendetBei() ?: '—',

                        ]"

                        editAction="$dispatch('object-edit', { typ: 'sshkey', id: {{ $eintrag->id }} })"
                        can="sshkey_update"
                    />
