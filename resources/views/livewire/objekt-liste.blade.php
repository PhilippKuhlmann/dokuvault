{{-- Kopfzeile mit Suche und Anlegen-Knopf, darunter die Karten des Typs. --}}
<div>
    <x-sitetopmenu :neu="false" :titel="__(config('custom.trashables')[$typ][1] ?? $einzahl)">
        <input wire:model.live.debounce.300ms="search" type="search"
            placeholder="{{ __('Suchen …') }}"
            class="w-44 rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-cerulean-500 focus:ring-cerulean-500 sm:w-64 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />

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
