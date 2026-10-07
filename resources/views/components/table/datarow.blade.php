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
        @if ($key == 'pfad')
            {{-- Ein Weg innerhalb der Anwendung. Der Schluessel sagt es, nicht
                 der Wert: Ob "/24" ein Pfad ist oder eine Netzmaske, kann die
                 Tabelle nicht wissen - hier weiss es die Ansicht. --}}
            <td scope="row" class="py-2.5 px-4">
                @if ($ziel = \App\Support\Adresse::pfad($value))
                    <a href="{{ $ziel }}" class="text-cerulean-500 hover:text-cerulean-600">{{ $value }}</a>
                @else
                    {{ $value }}
                @endif
            </td>
        @elseif ($key == 'download')
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
        @elseif (str_starts_with((string) $key, 'liste'))
            {{-- A list of names (e.g. the groups of an AD user). Folded to a
                 count, so a user in twenty groups does not stretch every row;
                 a click unfolds the whole list in place. Key 'liste:Gruppen'
                 - the part after the colon names what is counted. --}}
            <td scope="row" class="py-2.5 px-4 align-top">
                @if (count($value) === 0)
                    <span class="text-gray-400 dark:text-gray-500">—</span>
                @else
                    <div x-data="{ offen: false }">
                        <button type="button" @click="offen = !offen"
                            class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            {{ count($value) }} {{ __(\Illuminate\Support\Str::after((string) $key, ':')) }}
                            <svg class="h-3 w-3 transition-transform" :class="offen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                        <ul x-show="offen" x-cloak class="mt-1.5 max-h-60 space-y-0.5 overflow-y-auto text-xs text-gray-700 dark:text-gray-200">
                            @foreach ($value as $name)
                                <li>{{ $name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </td>
        @elseif ($key == 'credentials')
            {{-- $value ist hier das Geraet selbst, nicht ein Wert: Die verknuepften
                 Zugangsdaten holt sich die Komponente daraus. --}}
            <td scope="row" class="py-2.5 px-4"><x-credentialsinline :device="$value" /></td>
        @elseif ($key == 'geheim')
            {{-- $value ist [Modell, Feldname]: Das Geheimnis kommt erst auf
                 Klick über den Server (App\Livewire\GeheimFeld), nicht als
                 Klartext ins ausgelieferte HTML. Vorher stand der Wert im DOM
                 und war nur per JavaScript verdeckt - in einer Liste mit vielen
                 Zeilen also alle Kennwörter auf einmal. --}}
            {{-- Without a stored value a dash, not the masked field: an AD user
                 without a password (by hand or from the agent) looked like one
                 with a password, and "show" revealed nothing. Checked on the
                 decrypted value - some models encrypt an empty string, too. --}}
            <td scope="row" class="py-2.5 px-4">
                @if (filled($value[0]->{$value[1]}))
                    <livewire:geheim-feld :modell="get_class($value[0])" :id="$value[0]->id" :feld="$value[1]"
                        width="w-40" :key="'gf-'.class_basename($value[0]).'-'.$value[0]->id.'-'.$value[1]" />
                @else
                    <span class="text-gray-400 dark:text-gray-500">—</span>
                @endif
            </td>


        @elseif ($key == 'status')
            {{-- true/false/null als Haken, Kreuz oder Strich. --}}
            <td scope="row" class="py-2.5 px-4"><x-statusicon :value="$value" /></td>
        @elseif ($key == 'einladung')
            {{-- $value ist der Benutzer selbst: Ob die Einladung offen oder
                 abgelaufen ist, haengt an der Frist aus config/auth.php - das
                 weiss das Modell, nicht die Tabelle. --}}
            <td scope="row" class="py-2.5 px-4 text-gray-900 dark:text-gray-100"><x-einladung :user="$value" /></td>
        @elseif ($key == 'zweitestufe')
            {{-- Eigener Schluessel und nicht 'status': Dort gibt es nur an, aus
                 und unbekannt - "verlangt, aber noch nicht eingerichtet" ist
                 keins davon. --}}
            <td scope="row" class="py-2.5 px-4"><x-zweitestufe :zustand="$value" /></td>
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
        <div class="flex items-center justify-end gap-2">
            @if ($editUrl || $editAction)
                @can($can)
                    @if ($editAction)
                        <x-input.symbolknopf wire:click="{{ $editAction }}" :titel="__('Bearbeiten')">
                            <x-svg.edit class="h-5 w-5" />
                        </x-input.symbolknopf>
                    @else
                        <x-input.symbolknopf :href="$editUrl" :titel="__('Bearbeiten')">
                            <x-svg.edit class="h-5 w-5" />
                        </x-input.symbolknopf>
                    @endif
                @endcan
            @endif

            {{-- Beides muss da sein. Vorher stand hier @isset($delUrl) - und
                 weil die Prop mit '' vorbelegt ist, war das immer wahr: Der
                 Knopf erschien in jeder Zeile und sein Formular zeigte auf die
                 aktuelle Adresse statt auf eine Loeschen-Route. --}}
            @if ($delUrl && $canDel)
                @can($canDel)
                    <x-loeschdialog :url="$delUrl">
                        <x-slot:ausloeser>
                            <x-input.symbolknopf x-on:click="offen = true" :titel="__('Löschen')" ton="rot">
                                <x-svg.trash class="h-5 w-5" />
                            </x-input.symbolknopf>
                        </x-slot:ausloeser>
                    </x-loeschdialog>
                @endcan
            @endif
        </div>
    </td>
</tr>
