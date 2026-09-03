{{-- editAction statt editUrl: Dann oeffnet der Stift ein Livewire-Modal
     statt eine eigene Seite zu laden - wie bei x-show.header. --}}
{{-- inaktiv: Die Zeile bleibt lesbar, tritt aber zurueck - ein gesperrtes
     Konto ist dokumentiert und trotzdem nicht das, wonach man sucht. --}}
@props(['values', 'editUrl' => null, 'can', 'canDel' => '', 'delUrl' => '', 'editAction' => null, 'inaktiv' => false])

<tr @class([
    'bg-white border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700/50',
    'opacity-60' => $inaktiv,
])>
    @foreach ($values as $key => $value)
        @if ($key == 'download')
            @if ($value)
                <td scope="row" class="py-2.5 px-4">
                    <a href="{{ $value }}" target="_blank"  class="text-cerulean-500 hover:text-cerulean-600">{{ __('Download') }}</a>
                </td>
            @else
                <td scope="row" class="py-2.5 px-4">
                    <a disabled class="text-gray-500">{{ __('Download') }}</a>
                </td>
            @endif
        @elseif ($key == 'eol')
            {{-- $value ist das Betriebssystem selbst: Datum und Zustand kommen
                 aus derselben Quelle wie das Abzeichen in den Geraetelisten. --}}
            <td scope="row" class="py-2.5 px-4">
                @if ($value?->eol_date)
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $value->eol_date->format('d.m.Y') }}</span>
                        <x-eol :os="$value" />
                    </div>
                @else
                    <span class="text-gray-400 dark:text-gray-500">—</span>
                @endif
            </td>
        @elseif ($key == 'credentials')
            {{-- $value ist hier das Geraet selbst, nicht ein Wert: Die verknuepften
                 Zugangsdaten holt sich die Komponente daraus. --}}
            <td scope="row" class="py-2.5 px-4"><x-credentialsinline :device="$value" /></td>
        @elseif ($key == 'password')
        <td scope="row" class="py-2.5 px-4">
            <div class="" x-data="{ show: true, copied: false }">

                <div class="relative">
                    <input x-ref="pw" placeholder="" :type="show ? 'password' : 'text'"
                        class="text-sm w-fit p-0 pr-16 bg-transparent text-gray-900 dark:text-gray-100 border-0"
                        value="{{ $value }}"
                        disabled>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2 text-sm leading-5">

                        <button type="button" tabindex="-1" title="{{ __('Passwort kopieren') }}"
                            @click="copyText($refs.pw.value); copied = true; setTimeout(() => copied = false, 1500)"
                            class="text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                            </svg>
                            <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-green-600 dark:text-green-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" @click="show = !show"
                        :class="{ 'hidden': !show, 'block': show }" class="w-6 h-6 text-gray-900 dark:text-gray-100 cursor-pointer">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          </svg>


                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" @click="show = !show"
                          :class="{ 'block': !show, 'hidden': show }" class="w-6 h-6 text-gray-900 dark:text-gray-100 cursor-pointer">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                          </svg>

                    </div>
                </div>
            </div>
        </td>


        @elseif ($key == 'status')
            {{-- true/false/null als Haken, Kreuz oder Strich. --}}
            <td scope="row" class="py-2.5 px-4"><x-statusicon :value="$value" /></td>
        @elseif ($key == 'rackface')
            {{-- $value ist der Katalogeintrag selbst: Ob eine Zeichnung oder ein
                 hochgeladenes Foto erscheint, entscheidet der Eintrag.

                 Das Seitenverhaeltnis ist dasselbe wie im viewBox der Zeichnung:
                 1086 zu 100 je Hoeheneinheit, die Masse einer 19"-Blende. Ein
                 Kasten mit fester Hoehe machte aus einer 1-HE-Blende einen
                 Kloetzchen-Server - und alle Hoehen saehen gleich aus. --}}
            <td scope="row" class="py-2.5 px-4">
                <div class="w-48 text-gray-500 dark:text-gray-400"
                    style="aspect-ratio: 1086 / {{ 100 * max(1, (int) $value->height_units) }};">
                    <x-rack.face :appearance="$value->appearance ?: 'blank'" :he="$value->height_units"
                        :image="$value->bildUrl()" :drawing="$value->drawing ?? null" />
                </div>
            </td>
        @elseif ($key == 'fingerprint')
            {{-- Gekuerzt mit Kopier-Knopf: vollstaendig bricht er auf fuenf
                 Zeilen um und bestimmt die Zeilenhoehe. --}}
            <td scope="row" class="py-2.5 px-4"><x-fingerprint :value="$value" /></td>
        @elseif ($ziel = \App\Support\Adresse::sicher($value))
            {{-- Am Wert, nicht am Spaltennamen: Die Spalte hiess frueher "url",
                 und ein Feld wie "management_url" wurde deshalb nicht verlinkt.
                 Nur http und https - siehe App\Support\Adresse.

                 Nach den eigenen Spalten, nicht davor: "download" ist ebenfalls
                 eine Adresse, soll aber "Download" heissen und nicht die URL
                 zeigen. --}}
            <td scope="row" class="py-2.5 px-4 break-all">
                <a href="{{ $ziel }}" target="_blank" rel="noopener noreferrer" class=" text-cerulean-500 hover:text-cerulean-600">{{ $value }}</a>
            </td>
        @else
            <td scope="row" class="py-2.5 px-4 text-gray-900 dark:text-gray-100">
                {{ $value }}
            </td>
        @endif

    @endforeach


    {{-- Bearbeiten und Löschen nebeneinander, beide als quadratischer
         Symbolknopf - so wie im Papierkorb und in den Geraetelisten. Vorher
         stand unter dem Stift ein roter Textknopf "Löschen!", der die Zeile
         hoeher machte und aus der Reihe fiel. --}}
    <td class="py-2.5 px-4">
        @php
            $knopfKlassen = 'inline-flex items-center justify-center w-9 h-9 rounded-lg border shadow-sm transition-colors';
            $stiftKlassen = $knopfKlassen.' border-gray-200 bg-white text-cerulean-600 hover:bg-cerulean-50 hover:border-cerulean-300 dark:bg-gray-800 dark:border-gray-600 dark:text-cerulean-400 dark:hover:bg-gray-700';
            $muellKlassen = $knopfKlassen.' border-gray-200 bg-white text-red-600 hover:bg-red-50 hover:border-red-300 dark:bg-gray-800 dark:border-gray-600 dark:text-red-400 dark:hover:bg-gray-700';
        @endphp

        <div class="flex items-center justify-end gap-2">
            @if ($editUrl || $editAction)
                @can($can)
                    @if ($editAction)
                        <button type="button" wire:click="{{ $editAction }}" title="{{ __('Bearbeiten') }}" class="{{ $stiftKlassen }}">
                            <x-svg.edit class="h-5 w-5" />
                        </button>
                    @else
                        <a href="{{ $editUrl }}" title="{{ __('Bearbeiten') }}" class="{{ $stiftKlassen }}">
                            <x-svg.edit class="h-5 w-5" />
                        </a>
                    @endif
                @endcan
            @endif

            {{-- Beides muss da sein. Vorher stand hier @isset($delUrl) - und
                 weil die Prop mit '' vorbelegt ist, war das immer wahr: Der
                 Knopf erschien in jeder Zeile und sein Formular zeigte auf die
                 aktuelle Adresse statt auf eine Loeschen-Route. --}}
            @if ($delUrl && $canDel)
                @can($canDel)
                    <form method="POST" action="{{ $delUrl }}"
                        onsubmit="return confirm('{{ __('Objekt wirklich unwiderruflich löschen?') }}')">
                        @csrf
                        @method('delete')
                        <button type="submit" title="{{ __('Löschen') }}" class="{{ $muellKlassen }}">
                            <x-svg.trash class="h-5 w-5" />
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </td>
</tr>
