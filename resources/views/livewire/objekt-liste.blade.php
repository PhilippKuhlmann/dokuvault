{{-- Kopfzeile mit Suche und Anlegen-Knopf, darunter die Karten des Typs.
     Suchfeld wie in der VLAN-Liste: Lupe im Feld, sucht waehrend des Tippens. --}}
<div>
    <x-sitetopmenu :neu="false" :titel="__(config('custom.trashables')[$typ][1] ?? $einzahl)">
        {{-- Ohne Filterleiste steht die Suche hier oben. Gibt es eine, zieht
             sie dort hinein: Suche und Filter engen dasselbe ein und gehoeren
             nebeneinander - getrennt sah es aus wie zwei Bedienfelder. --}}
        @unless ($filterDefinition || $sortierungen)
            <label class="relative">
                <span class="sr-only">{{ __('Liste durchsuchen') }}</span>
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                {{-- Kein eigenes rounded: x-input.field merged Klassen statt sie zu
                     ersetzen, zwei Radius-Klassen wuerden sich streiten. --}}
                <x-input.field wire:model.live.debounce.300ms="search" type="search"
                    :placeholder="__('Suche')" class="w-56 pl-9 pr-3 py-2 text-sm" />
            </label>
        @endunless

        <livewire:objekt-formular :typ="$typ" :customer="$customer" :key="'formular-'.$typ" />
    </x-sitetopmenu>

    {{-- Filterleiste nur, wo der Typ etwas anzubieten hat: Eine Lizenz hat
         eine Laufzeit, ein Drucker nicht - eine leere Leiste ueber jeder Liste
         waere nur Rauschen. --}}
    @if ($filterDefinition || $sortierungen)
        <div class="m-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0">
                    <x-input.label :value="__('Suche')" />
                    <x-input.field wire:model.live.debounce.300ms="search" type="search"
                        :placeholder="__('Suche')" class="mt-1 w-full" />
                </div>

                {{-- w-full und min-w-0: Ein select richtet sich nach seiner
                     laengsten Option. "Windows Server 2025 Datacenter" machte
                     das Feld 113 Pixel breiter als seine Rasterzelle - der
                     Pfeil sitzt am rechten Rand und lag damit ausserhalb der
                     Karte, das Feld sah aus wie ein Textfeld. --}}
                @foreach ($filterDefinition as $def)
                    <div class="min-w-0" wire:key="filter-{{ $def['name'] }}">
                        <x-input.label :value="__($def['label'])" />
                        <x-input.select :name="'filter.'.$def['name']" wire:model.live="filter.{{ $def['name'] }}" class="mt-1 w-full">
                            <option value="">{{ __($def['alle'] ?? 'Alle') }}</option>
                            @foreach ($def['optionen'] as $wert => $beschriftung)
                                <option value="{{ $wert }}">{{ __($beschriftung) }}</option>
                            @endforeach
                        </x-input.select>
                    </div>
                @endforeach

                @if ($sortierungen)
                    <div class="min-w-0">
                        <x-input.label :value="__('Sortierung')" />
                        <x-input.select name="sortierung" wire:model.live="sortierung" class="mt-1 w-full">
                            @foreach ($sortierungen as $schluessel => $eine)
                                <option value="{{ $schluessel }}">{{ __($eine[0]) }}</option>
                            @endforeach
                        </x-input.select>
                    </div>
                @endif
            </div>

            @if ($gefiltert)
                <div class="mt-4 flex border-t border-gray-100 pt-4 dark:border-gray-700">
                    <button type="button" wire:click="zuruecksetzen"
                        class="ml-auto text-sm text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">
                        {{ __('Filter zurücksetzen') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Zwei Darstellungen im Bestand: Die meisten Listen zeigen Karten, einige
         eine Tabelle. Welche es ist, entscheidet die Datei beim Typ - eine
         erzwungene Vereinheitlichung waere ein zweiter Umbau in einem. --}}
    @php ($alsTabelle = view()->exists($typ.'._zeile'))

    @if ($alsTabelle && $eintraege->isNotEmpty())
        <div class="m-3">
            <x-table.main>
                @include($typ.'._spalten')
                <x-table.body>
                    @foreach ($eintraege as $eintrag)
                        <div wire:key="{{ $typ }}-{{ $eintrag->id }}" class="contents">
                            @include($typ.'._zeile', ['eintrag' => $eintrag, 'customer' => $customer])
                        </div>
                    @endforeach
                </x-table.body>
            </x-table.main>
        </div>
    @endif

    @forelse ($eintraege as $eintrag)
        @unless ($alsTabelle)
            <div wire:key="{{ $typ }}-{{ $eintrag->id }}">
                @include($typ.'._karte', ['eintrag' => $eintrag, 'customer' => $customer])
            </div>
        @endunless
    @empty
        @if ($search !== '')
            {{-- Unterschied mit Ansage: "nichts gefunden" ist etwas anderes als
                 "noch nichts angelegt". --}}
            <div class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                {{ __('Kein Eintrag passt zu ":begriff".', ['begriff' => $search]) }}
            </div>
        @elseif ($gefiltert)
            <div class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                {{ __('Kein Eintrag passt zu den Filtern.') }}
            </div>
        @else
            <x-emptystate />
        @endif
    @endforelse

    <div class="px-3 pb-3">{{ $eintraege->links() }}</div>
</div>
