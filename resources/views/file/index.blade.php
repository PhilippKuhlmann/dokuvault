<x-app-layout :$customer>

    {{-- Die Seite hatte bisher keine Kopfzeile: kein Titel, das Formular klebte
         oben am Rand. Jetzt wie jede andere Liste - Titel links, Zaehler
         rechts. --}}
    <x-sitetopmenu :neu="false" :titel="__('Dateien')">
        @if ($files->total() > 0)
            {{-- Mehrzahl von Hand statt mit trans_choice: Es gibt keine
                 lang/de.json, und trans_choice greift bei einem unbekannten
                 Schluessel auf die Ausweichsprache zurueck - dann stand dort
                 "1 file", waehrend der Rest der Seite deutsch war. --}}
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $files->total() }} {{ $files->total() === 1 ? __('Datei') : __('Dateien') }}
            </span>
        @endif
    </x-sitetopmenu>

    @can('file_create')
        {{-- Der Upload als eigene Karte statt als lose Zeile. Der Dateiname
             fuellt sich beim Auswaehlen von selbst, solange das Feld leer ist -
             wer eine Datei aussucht, hat den Namen schon im Dateidialog
             gelesen. --}}
        <div class="mx-3 mt-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            x-data="{
                dateiname: '',
                uebernehmen(feld) {
                    const datei = feld.files[0];
                    if (! datei) return;
                    this.groesse = datei.size;
                    if (! this.dateiname) {
                        this.dateiname = datei.name.replace(/\.[^.]+$/, '');
                    }
                },
                groesse: null,
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

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex min-w-0 flex-1 flex-col">
                        <x-input.label for="file" :value="__('Datei')" />
                        <x-input.file id="file" name="file" class="mt-1" x-on:change="uebernehmen($el)" />

                        {{-- Die Groesse noch vor dem Hochladen: 20 MB sind das
                             Limit, und das erfaehrt man sonst erst hinterher. --}}
                        <span x-show="groesse !== null" x-cloak
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="lesbar()"></span>
                            <span x-show="groesse > 20971520" class="text-red-600 dark:text-red-400">
                                — {{ __('über dem Limit von 20 MB') }}
                            </span>
                        </span>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col">
                        <x-input.label for="name" :value="__('Bezeichnung')" />
                        <x-input.field id="name" name="name" x-model="dateiname" class="mt-1"
                            :placeholder="__('z. B. Wartungsvertrag 2026')" required />
                    </div>

                    <x-input.button :label="__('Hochladen')" class="shrink-0" />
                </div>
            </form>
        </div>
    @endcan

    @if ($files->total() > 0)
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

                        <tr class="border-t border-hawkes-blue-200 bg-hawkes-blue-100 dark:border-gray-600 dark:bg-gray-700">
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
                                        <form method="POST" action="{{ route('file.destroy', [$customer, $file]) }}"
                                            x-data
                                            x-on:submit="$event.preventDefault(); if (confirm('{{ __('Diese Datei wirklich löschen?') }}')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="{{ __('Löschen') }}"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-red-600 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                                                <x-svg.trash class="h-5 w-5" />
                                            </button>
                                        </form>
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
        <x-emptystate />
    @endif

</x-app-layout>
