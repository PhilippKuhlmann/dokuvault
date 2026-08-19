{{-- Knopf plus Modal fuer jeden Typ aus config/forms.php.

     Aufbau und Knopfleiste folgen dem VLAN-Modal: Loeschen links abgesetzt,
     Abbrechen und Speichern rechts, die Rueckfrage ersetzt die Felder. --}}
<div class="inline">
    @can($typ.'_create')
        <button type="button" wire:click="neu"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('Neu') }}
        </button>
    @endcan

    @if ($offen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="$wire.abbrechen()">

            <div class="max-h-[90vh] w-full {{ $spalten > 1 ? 'max-w-3xl' : ($mitBloecken && $bearbeiteId ? 'max-w-2xl' : 'max-w-md') }} overflow-y-auto rounded-xl border border-gray-200 bg-white px-5 pt-5 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">

                @unless ($loeschenGefragt)
                    <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                        {{ $bearbeiteId ? __($einzahl).' '.__('bearbeiten') : __('Neu').': '.__($einzahl) }}
                    </div>

                    {{-- Zwei Spalten, wo es viele Felder sind: Zwanzig Eingaben untereinander
                         ergeben eine Scrollstrecke, bei der man den Anfang aus den
                         Augen verliert. Felder mit 'breit' spannen ueber beide. --}}
                    <div @class([
                        'gap-x-4 gap-y-3',
                        'flex flex-col' => $spalten === 1,
                        'grid grid-cols-1 sm:grid-cols-2' => $spalten > 1,
                    ])>
                        @foreach ($felder as $feld)
                            <div wire:key="feld-{{ $feld['name'] }}"
                                @class([
                                    'flex flex-col',
                                    'sm:col-span-2' => $spalten > 1 && ($feld['breit'] ?? false),
                                ])
                                {{-- Felder, die nur zu einer Bauform gehoeren: Ein
                                     Standserver hat keine Einbautiefe. --}}
                                @if (! empty($feld['sichtbar_wenn']))
                                    x-show="$wire.form.{{ array_key_first($feld['sichtbar_wenn']) }} === '{{ reset($feld['sichtbar_wenn']) }}'"
                                    x-cloak
                                @endif>
                                @unless ($feld['type'] === 'dienste')
                                    <x-input.label :value="__($feld['label'])" />
                                @endunless

                                @if ($feld['type'] === 'standort')
                                    <x-input.select :name="$feld['name']" wire:model="form.{{ $feld['name'] }}" class="mt-1">
                                        <option value="">— {{ __('bitte wählen') }} —</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </x-input.select>
                                @elseif ($feld['type'] === 'dienste')
                                    {{-- Dieselbe Auswahl wie auf der Seite: Kacheln,
                                         Katalog aus der Administration, Freitext.
                                         Sie meldet jede Aenderung an Livewire, statt
                                         ein verstecktes Formularfeld zu fuellen. --}}
                                    <x-create.dienste :default="$form[$feld['name']] ?? ''"
                                        wire-model="form.{{ $feld['name'] }}" />
                                @elseif ($feld['type'] === 'optionen')
                                    {{-- Feste Liste aus config/custom.php, etwa die
                                         Bauform eines Servers. --}}
                                    <x-input.select :name="$feld['name']" wire:model.live="form.{{ $feld['name'] }}" class="mt-1">
                                        @foreach (config($feld['quelle']) as $wert => $beschriftung)
                                            <option value="{{ $wert }}">{{ __($beschriftung) }}</option>
                                        @endforeach
                                    </x-input.select>
                                @elseif ($feld['type'] === 'schalter')
                                    <label class="mt-1 inline-flex items-center gap-2">
                                        <input type="checkbox" wire:model="form.{{ $feld['name'] }}"
                                            class="rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Ja') }}</span>
                                    </label>
                                @elseif ($feld['type'] === 'auswahl')
                                    <x-input.select :name="$feld['name']" wire:model="form.{{ $feld['name'] }}" class="mt-1">
                                        <option value="">— {{ __('bitte wählen') }} —</option>
                                        @foreach (($auswahlen[$feld['name']] ?? []) as $id => $beschriftung)
                                            <option value="{{ $id }}">{{ $beschriftung }}</option>
                                        @endforeach
                                    </x-input.select>
                                @else
                                    <x-input.text wire:model="form.{{ $feld['name'] }}"
                                        type="{{ $feld['type'] }}" class="mt-1" />
                                @endif

                                @error('form.'.$feld['name'])
                                    <span class="mt-1 text-xs text-red-600">{{ $message }}</span>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                    @if ($mitBloecken)
                        @if ($bearbeiteId)
                            {{-- Eigene Livewire-Bloecke mit eigenem Speichern -
                                 deshalb ausserhalb der Felder darueber. --}}
                            {{-- Keine eigene Trennlinie: Der Block bringt im
                                 eingebetteten Zustand selbst eine mit, zwei
                                 uebereinander sahen aus wie ein Fehler. --}}
                            <div class="mt-1">
                                <livewire:device-ip-addresses :model="$objekt" :customer="$objekt->customer" eingebettet randlos
                                    :key="'ip-'.$typ.'-'.$objekt->id" />
                                <livewire:device-credentials :model="$objekt" :customer="$objekt->customer" eingebettet randlos
                                    :key="'zug-'.$typ.'-'.$objekt->id" />
                            </div>
                        @else
                            {{-- Beim Anlegen haengt noch nichts am Objekt. Der Hinweis
                                 stand auch im alten Formular, damit das Fehlen nicht
                                 wie ein Mangel aussieht. --}}
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('IP-Adressen und Zugangsdaten lassen sich eintragen, sobald der Eintrag angelegt ist.') }}
                            </p>
                        @endif
                    @endif
                @endunless

                @if ($loeschenGefragt)
                    {{-- Die Rueckfrage ersetzt die Felder, statt unter ihnen zu
                         haengen: Bei vielen Feldern stand sie sonst ausserhalb
                         des Sichtbereichs. --}}
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                        <div class="text-sm font-medium text-red-800 dark:text-red-300">
                            {{ __($einzahl) }} {{ __('löschen?') }}
                        </div>
                        <p class="mt-1 text-xs text-red-700/80 dark:text-red-400/80">
                            {{ __('Der Eintrag landet im Papierkorb und lässt sich von dort wiederherstellen.') }}
                        </p>

                        <div class="mt-4 flex justify-end gap-2">
                            <x-input.button type="button" color="gray"
                                wire:click="$set('loeschenGefragt', false)" :label="__('Abbrechen')" />
                            <x-input.button type="button" color="red" wire:click="loeschen" :label="__('Löschen')" />
                        </div>
                    </div>
                @else
                    {{-- Am unteren Rand haften: Mit den Bloecken fuer IP-Adressen und
                         Zugangsdaten wird das Modal so hoch, dass Speichern sonst
                         ausserhalb des Bildes liegt und erst gescrollt werden muss.
                         Der eigene Hintergrund verhindert, dass Felder darunter
                         durchscheinen. --}}
                    <div class="sticky bottom-0 -mx-5 mt-5 flex flex-wrap items-center justify-end gap-2 rounded-b-xl border-t border-gray-100 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
                        @if ($bearbeiteId)
                            @can($typ.'_delete')
                                <x-input.button type="button" color="red" class="mr-auto"
                                    wire:click="$set('loeschenGefragt', true)" :label="__('Löschen')" />
                            @endcan
                        @endif

                        {{-- abbrechen() statt offen=false: sonst bleibt bearbeiteId stehen
                             und das naechste "Neu" oeffnet das Bearbeiten-Modal. --}}
                        <x-input.button type="button" color="gray" wire:click="abbrechen" :label="__('Abbrechen')" />
                        <x-input.button type="button" wire:click="speichern"
                            :label="$bearbeiteId ? __('Speichern') : __('Anlegen')" />
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
