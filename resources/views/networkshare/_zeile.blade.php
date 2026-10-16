{{-- One network drive per row, the drive letter first. Whether the onboarding
     form offers it is set in the checklist settings. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->drive_letter ? $eintrag->drive_letter.':' : '—',
                            $eintrag->name,
                            $eintrag->path,
                            $eintrag->host?->name ?? '—',
                            $eintrag->readGroup?->name ?? '—',
                            $eintrag->writeGroup?->name ?? '—',
                            $eintrag->sensitive ? __('nötig') : '—',
                        ]"
                        editAction="$dispatch('objekt-bearbeiten', { typ: 'networkshare', id: {{ $eintrag->id }} })"
                        can="networkshare_update"
                    />
