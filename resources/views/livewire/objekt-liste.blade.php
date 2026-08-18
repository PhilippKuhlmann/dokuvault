{{-- Kopfzeile mit Suche und Anlegen-Knopf, darunter die Karten des Typs.
     Suchfeld wie in der VLAN-Liste: Lupe im Feld, sucht waehrend des Tippens. --}}
<div>
    <x-sitetopmenu :neu="false" :titel="__(config('custom.trashables')[$typ][1] ?? $einzahl)">
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

        <livewire:objekt-formular :typ="$typ" :customer="$customer" :key="'formular-'.$typ" />
    </x-sitetopmenu>

    @forelse ($eintraege as $eintrag)
        <div wire:key="{{ $typ }}-{{ $eintrag->id }}">
            @include($typ.'._karte', ['eintrag' => $eintrag, 'customer' => $customer])
        </div>
    @empty
        @if ($search !== '')
            {{-- Unterschied mit Ansage: "nichts gefunden" ist etwas anderes als
                 "noch nichts angelegt". --}}
            <div class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                {{ __('Kein Eintrag passt zu ":begriff".', ['begriff' => $search]) }}
            </div>
        @else
            <x-emptystate />
        @endif
    @endforelse

    <div class="px-3 pb-3">{{ $eintraege->links() }}</div>
</div>
