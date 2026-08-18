{{-- Ein Eintrag in der Liste. Als eigenes Teilstueck, damit die
     generische Liste (App\Livewire\ObjektListe) es einbinden kann -
     die Karte bleibt beim Typ, weil gerade ihre Unterschiede die
     Information tragen. --}}
                    <x-table.datarow
                        :values="[
                            $eintrag->first_name . ' ' . $eintrag->last_name,
                            $eintrag->role,
                            $eintrag->phone,
                            $eintrag->mail,

                        ]"

                        editAction="$dispatch('objekt-bearbeiten', { typ: 'contactperson', id: {{ $eintrag->id }} })"
                        can="contactperson_update"

                    />

                
