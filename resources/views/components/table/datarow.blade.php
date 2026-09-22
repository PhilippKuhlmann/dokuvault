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
            <td scope="row" class="py-2.5 px-4">
                <livewire:geheim-feld :modell="get_class($value[0])" :id="$value[0]->id" :feld="$value[1]"
                    width="w-40" :key="'gf-'.class_basename($value[0]).'-'.$value[0]->id.'-'.$value[1]" />
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
