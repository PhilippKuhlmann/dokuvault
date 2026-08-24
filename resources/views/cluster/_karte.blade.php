{{-- Ein Cluster in der Liste. Die Karte bleibt beim Typ. --}}
<x-card>
    <x-slot:head>
        <x-show.header can="cluster_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'cluster', id: {{ $eintrag->id }} })">
            {{ $eintrag->name }}

            {{-- Die Art klein hinter den Namen: Sie ist die eigentliche Aussage
                 eines Clusters, nicht ein Nachschlagewert weiter unten. --}}
            @if ($eintrag->typBezeichnung())
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $eintrag->typBezeichnung() }}</span>
            @endif

            <x-slot:kernwerte>
                <x-kernwert :label="__('Knoten')">{{ $eintrag->servers->count() }}</x-kernwert>
            </x-slot>
        </x-show.header>
    </x-slot>

    <x-slot:body>

        {{-- Die Knoten mit Betriebssystem: Beim Blick auf einen Cluster ist die
             erste Frage, ob alle Knoten auf demselben Stand sind - ein Knoten
             mit aelterem System faellt so auf.

             Dieselbe Tabellenform wie x-minitablecard daneben, nicht einzelne
             Chips: Der Kartenkoerper laeuft in CSS-Spalten, und darin brach
             jeder Chip auf eine eigene Zeile um - die Kaestchen standen
             unterschiedlich breit untereinander. Untereinander ausgerichtet
             wird die Wiederholung des Systemnamens ausserdem zur Aussage
             ("alle gleich") statt zu Rauschen.

             break-inside-avoid und mb-5 wie bei den uebrigen Bloecken, sonst
             reisst die Liste mitten im Umbruch auseinander. --}}
        <div class="w-full mb-5 break-inside-avoid">
            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('Knoten') }}
            </div>

            @if ($eintrag->servers->isEmpty())
                {{-- Zugeordnet wird am Server, nicht hier - sonst gaebe es zwei
                     Wege fuer dieselbe Angabe. --}}
                <div class="text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Noch kein Server zugeordnet. Der Cluster lässt sich am Server auswählen.') }}
                </div>
            @else
                <div class="text-sm dark:text-gray-100">
                    <table class="w-full">
                        @foreach ($eintrag->servers as $server)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                                <td class="py-1 pr-6 align-top break-words text-gray-900 dark:text-gray-100">
                                    {{ $server->name }}
                                </td>
                                <td class="py-1 break-words align-top text-gray-500 dark:text-gray-400">
                                    @if ($server->operatingSystem)
                                        <span class="align-middle">{{ $server->operatingSystem->name }}</span>
                                        <x-eol :os="$server->operatingSystem" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <x-minitablecard :title="__('Cluster')" :array="[
            'Art' => $eintrag->typBezeichnung(),
            'Notiz' => $eintrag->note,
        ]" />

    </x-slot>
</x-card>
