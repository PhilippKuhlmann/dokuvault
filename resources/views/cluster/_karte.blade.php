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
             mit aelterem System faellt so auf. --}}
        <div class="w-full">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                {{ __('Knoten') }}
            </div>

            @if ($eintrag->servers->isEmpty())
                {{-- Zugeordnet wird am Server, nicht hier - sonst gaebe es zwei
                     Wege fuer dieselbe Angabe. --}}
                <div class="text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Noch kein Server zugeordnet. Der Cluster lässt sich am Server auswählen.') }}
                </div>
            @else
                <ul class="flex flex-wrap gap-2">
                    @foreach ($eintrag->servers as $server)
                        <li class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm dark:border-gray-700">
                            <span class="text-gray-900 dark:text-gray-100">{{ $server->name }}</span>
                            @if ($server->operatingSystem)
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $server->operatingSystem->name }}</span>
                                <x-eol :os="$server->operatingSystem" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-minitablecard :title="__('Cluster')" :array="[
            'Art' => $eintrag->typBezeichnung(),
            'Notiz' => $eintrag->note,
        ]" />

    </x-slot>
</x-card>
