@props([
    'url',
    'frage' => null,
    'hinweis' => null,
    'bestaetigen' => null,
    'methode' => 'delete',
])

{{--
    Die Rueckfrage vor dem Loeschen - als Blatt der Anwendung statt als
    confirm() des Browsers.

    Warum ueberhaupt: confirm() zeichnet das Betriebssystem, nicht die
    Anwendung. Der Kasten sieht auf jedem Rechner anders aus, traegt oben den
    Namen der Domain, kennt weder Dunkelmodus noch die Schrift der Anwendung -
    und beide Knoepfe sehen gleich aus, obwohl einer davon etwas loescht.

    x-teleport an den body: Die Tabellen stehen in overflow-x-auto. Ein
    fixiertes Element darin wird am Rand des Scrollrahmens abgeschnitten,
    sichtbar als halber Dialog.

    Der Abbrechen-Knopf bekommt den Fokus, nicht der rote: Wer den Dialog mit
    der Tastatur wegdrueckt, soll dabei nichts loeschen. Escape und ein Klick
    auf die Abdeckung tun dasselbe.

    Der Ausloeser kommt als Slot herein und setzt "offen = true" - so behaelt
    jede Aufrufstelle ihren eigenen Knopf: der quadratische Muelleimer in der
    Tabellenzeile, der rote Textknopf in der Loeschen-Karte.
--}}

@php
    $frage ??= __('Objekt wirklich unwiderruflich löschen?');
@endphp

<div x-data="{ offen: false }"
    x-init="$watch('offen', gezeigt => gezeigt && $nextTick(() => $refs.abbrechen?.focus()))"
    class="contents">

    {{ $ausloeser }}

    <template x-teleport="body">
        <div x-show="offen" x-cloak
            x-on:keydown.escape.window="offen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4">

            <div x-show="offen" x-transition.opacity.duration.150ms
                x-on:click="offen = false" aria-hidden="true"
                class="absolute inset-0 bg-gray-900/60 dark:bg-black/70"></div>

            <div x-show="offen"
                x-transition:enter="ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                role="dialog" aria-modal="true" aria-label="{{ $frage }}"
                class="relative w-full max-w-md rounded-lg border border-gray-200 bg-white shadow-xl
                       dark:border-gray-700 dark:bg-gray-800">

                {{-- Das Formular umschliesst den ganzen Dialog, nicht nur den
                     roten Knopf: Nur so koennen im Slot "felder" noch Eingaben
                     stehen, die mitgeschickt werden muessen - etwa das
                     Kennwort vor dem Loeschen des eigenen Kontos. Abbrechen
                     traegt type="button" und schickt deshalb nichts ab. --}}
                <form method="POST" action="{{ $url }}">
                    @csrf
                    @method($methode)

                    <div class="flex gap-4 p-6">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full
                                     bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400" aria-hidden="true">
                            <x-svg.trash class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-DINPro-bold text-gray-900 dark:text-gray-100">{{ $frage }}</h2>
                            @if ($hinweis)
                                <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $hinweis }}</p>
                            @endif

                            @isset($felder)
                                <div class="mt-4">{{ $felder }}</div>
                            @endisset
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                        <x-input.button type="button" color="gray" x-ref="abbrechen"
                            x-on:click="offen = false" :label="__('Abbrechen')" />

                        <x-input.button color="red" :label="$bestaetigen ?? __('Löschen')" />
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
