<div>
    {{-- Titel ausdruecklich: aus dem Routennamen abgeleitet waere er beim
         Livewire-Rerender leer. --}}
    <x-sitetopmenu :neu="false" :titel="__('Dateien')">
        <span class="text-sm text-gray-500 dark:text-gray-400">
            @if ($gefiltert)
                {{-- Im gefilterten Fall der Dativ: "1 von 83 Datei" waere
                     falsch, die Zahl davor bestimmt hier nicht den Fall. --}}
                {{ $files->total() }} {{ __('von') }} {{ $gesamt }}
                {{ $gesamt === 1 ? __('Datei') : __('Dateien') }}
            @else
                {{ $gesamt }} {{ $gesamt === 1 ? __('Datei') : __('Dateien') }}
            @endif
        </span>
    </x-sitetopmenu>

    @can('file_create')
        {{-- Der Upload bleibt ein gewoehnliches Formular: Es laedt die Seite
             neu, und die Liste zeigt die neue Datei danach von selbst. --}}
        <div class="mx-3 mt-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            x-data="{
                dateiname: '',
                groesse: null,
                uebernehmen(feld) {
                    const datei = feld.files[0];
                    if (! datei) return;
                    this.groesse = datei.size;
                    if (! this.dateiname) {
                        this.dateiname = datei.name.replace(/\.[^.]+$/, '');
                    }
                },
                lesbar() {
                    if (this.groesse === null) return '';
                    const e = ['B', 'KB', 'MB', 'GB'];
                    let w = this.groesse, i = 0;
                    while (w >= 1024 && i < e.length - 1) { w /= 1024; i++; }
                    return (i === 0 ? w : w.toFixed(1).replace('.', ',')) + ' ' + e[i];
                },
            }">

            <form method="POST" action="/{{ $customer->slug }}/file" enctype="multipart/form-data">
                @csrf

                {{-- Erst ab lg nebeneinander: Der Browser gibt dem Dateifeld eine
                     Mindestbreite von rund 270 Pixeln. --}}
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="flex min-w-0 flex-col lg:flex-1">
                        <x-input.label for="file" :value="__('Datei')" />
                        <x-input.file id="file" name="file" class="mt-1" x-on:change="uebernehmen($el)" />

                        {{-- Die Groesse noch vor dem Hochladen: 20 MB sind das
                             Limit, und das erfaehrt man sonst erst hinterher. --}}
                        <span x-show="groesse !== null" x-cloak class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="lesbar()"></span>
                            <span x-show="groesse > 20971520" class="text-red-600 dark:text-red-400">
                                — {{ __('über dem Limit von 20 MB') }}
                            </span>
                        </span>
                    </div>

                    <div class="flex min-w-0 flex-col lg:flex-1">
                        <x-input.label for="name" :value="__('Bezeichnung')" />
                        <x-input.field id="name" name="name" x-model="dateiname" class="mt-1"
                            :placeholder="__('z. B. Wartungsvertrag 2026')" required />
                    </div>

                    <x-input.button size="feld" :label="__('Hochladen')" class="w-full shrink-0 lg:w-auto" />
                </div>
            </form>
        </div>
    @endcan

    {{-- Filterleiste wie im Protokoll: Suche, Einschraenkung, Zeitraum. --}}
    <div class="m-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-input.label :value="__('Suche')" />
                <x-input.field wire:model.live.debounce.400ms="suche" type="search" class="mt-1 w-full"
                    :placeholder="__('Bezeichnung oder Endung …')" />
            </div>

            <div class="min-w-0">
                <x-input.label :value="__('Art')" />
                <x-input.select name="art" wire:model.live="art" class="mt-1 w-full">
                    <option value="">{{ __('Alle Arten') }}</option>
                    @foreach ($arten as $schluessel => $beschriftung)
                        <option value="{{ $schluessel }}">{{ __($beschriftung) }}</option>
                    @endforeach
                    <option value="datei">{{ __('Sonstige') }}</option>
                </x-input.select>
            </div>

            <div class="min-w-0">
                <x-input.label :value="__('Sortierung')" />
                <x-input.select name="sortierung" wire:model.live="sortierung" class="mt-1 w-full">
                    <option value="neueste">{{ __('Neueste zuerst') }}</option>
                    <option value="aelteste">{{ __('Älteste zuerst') }}</option>
                    <option value="name">{{ __('Bezeichnung') }}</option>
                    <option value="groesse">{{ __('Größte zuerst') }}</option>
                </x-input.select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-700">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Hochgeladen') }}</span>
            <div class="flex flex-wrap gap-1">
                @foreach ([0 => __('alles'), 1 => __('heute'), 7 => __('7 Tage'), 30 => __('30 Tage'), 90 => __('90 Tage')] as $wert => $beschriftung)
                    <button type="button" wire:click="$set('tage', {{ $wert }})"
                        @class([
                            'rounded-md border px-2.5 py-1 text-xs transition-colors',
                            'border-cerulean-500 bg-cerulean-50 text-cerulean-800 dark:bg-cerulean-950 dark:text-cerulean-100' => $tage === $wert,
                            'border-gray-200 text-gray-600 hover:border-cerulean-300 dark:border-gray-600 dark:text-gray-300' => $tage !== $wert,
                        ])>
                        {{ $beschriftung }}
                    </button>
                @endforeach
            </div>

            @if ($gefiltert)
                <button type="button" wire:click="zuruecksetzen"
                    class="ml-auto text-sm text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">
                    {{ __('Filter zurücksetzen') }}
                </button>
            @endif
        </div>
    </div>

    @if ($files->isNotEmpty())
        <div class="m-3">
            <x-table.main>
                <x-table.head :labels="[__('Datei'), __('Größe'), __('Hochgeladen'), '']" />

                <x-table.body>
                    @foreach ($files as $file)
                        @php
                            // Farbe je Art: Man ueberfliegt eine Liste nach Form
                            // und Farbe, nicht nach Text.
                            $farben = [
                                'pdf' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                'bild' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                'text' => 'bg-cerulean-50 text-cerulean-700 dark:bg-cerulean-900/30 dark:text-cerulean-300',
                                'tabelle' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                'archiv' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                'datei' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                            ];
                        @endphp

                        <tr wire:key="datei-{{ $file->id }}"
                            class="border-t border-hawkes-blue-200 bg-hawkes-blue-100 dark:border-gray-600 dark:bg-gray-700">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-11 shrink-0 items-center justify-center rounded-md text-xs font-DINPro-bold uppercase {{ $farben[$file->art()] }}">
                                        {{ Str::limit($file->extension, 4, '') ?: '—' }}
                                    </span>

                                    <a href="/{{ $customer->slug }}/file/{{ $file->id }}"
                                        class="min-w-0 break-words font-medium text-cerulean-700 hover:underline dark:text-cerulean-400">
                                        {{ $file->name }}.{{ $file->extension }}
                                    </a>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-2.5 text-sm tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $file->groesseLesbar() ?? '—' }}
                            </td>

                            {{-- Das genaue Datum im Hover: "vor 3 Tagen" liest sich
                                 schneller, aber manchmal braucht man den Tag. --}}
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-gray-600 dark:text-gray-300"
                                title="{{ $file->created_at->format('d.m.Y H:i') }}">
                                {{ $file->created_at->diffForHumans() }}
                            </td>

                            <td class="px-4 py-2.5">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/{{ $customer->slug }}/file/{{ $file->id }}" title="{{ __('Herunterladen') }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-cerulean-600 shadow-sm transition-colors hover:border-cerulean-300 hover:bg-cerulean-50 dark:border-gray-600 dark:bg-gray-800 dark:text-cerulean-400 dark:hover:bg-gray-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>

                                    @can('file_delete')
                                        <button type="button" wire:click="loeschen({{ $file->id }})"
                                            wire:confirm="{{ __('Diese Datei wirklich löschen?') }}"
                                            title="{{ __('Löschen') }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-red-600 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                                            <x-svg.trash class="h-5 w-5" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table.main>
        </div>

        <div class="px-3 pb-3">{{ $files->links() }}</div>
    @else
        {{-- Ohne Filter ist wirklich nichts da, mit Filter hat nur nichts
             gepasst - das ist ein Unterschied. --}}
        <x-emptystate :message="$gefiltert
            ? __('Keine Datei passt zu den Filtern.')
            : __('Noch keine Einträge vorhanden.')" />
    @endif
</div>
