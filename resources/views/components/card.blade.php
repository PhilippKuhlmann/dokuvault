@props(['plain' => false, 'raster' => null])

{{--
    Der Inhalt läuft in Spalten (CSS multi-column) statt in einer Flex-Reihe:
    Die Blöcke sind unterschiedlich hoch - Hardware hat sieben Zeilen,
    Zugangsdaten oft eine - und eine Flex-Reihe zieht alle auf die Höhe des
    längsten. Darunter klaffte dann Leere. In Spalten rutscht jeder Block
    direkt unter seinen Vorgänger.

    plain: für Karten, die ihren Inhalt selbst anordnen (Serverschränke).

    Der Rahmen kommt aus x-panel - dieselbe Fläche wie überall sonst.
    Eigen ist hier nur, was darin geschieht: der Kopf mit Trennlinie und
    der Satz in Spalten.

    Folding (design 1A, 26.11.22): a list card shows its head line only -
    name, kernwerte, actions - until it is opened. Nine servers fully open
    were six screens of scrolling to find one IP. The arrow, or a click on
    the free space of the head, opens it; "open all" of the list
    (kartenliste) and a jump from the search open it as well. Plain cards
    (rack, patch panel, EOL) arrange their content themselves and stay open.
--}}
@php
    // A list may be set to "always open" (forms.php 'klappbar' => false,
    // passed on by object-list): then no arrow, no folding, content shown.
    $listeKlappt = ! app()->bound('karten.klappbar') || app('karten.klappbar');
    $klappbar = ! $plain && trim($head) !== '' && $listeKlappt;
    // No @if inside a component tag: the binding comes as an attribute bag.
    $flaeche = new \Illuminate\View\ComponentAttributeBag($klappbar
        ? ['x-bind:class' => "offen ? 'ring-2 ring-cerulean-400/60 shadow-md dark:ring-cerulean-500/50' : ''"]
        : []);
@endphp

<div @if ($klappbar) x-data="karte" x-on:karten-alle.window="setzen($event.detail.offen)" @endif>
{{-- Opened, the card stands out with a blue frame: with "open all" one
     still sees where one card ends and the next begins. --}}
<x-panel polster="keins" data-karte-flaeche class="flex flex-col m-3 transition-shadow dark:text-gray-100" :attributes="$flaeche">
    @if (trim($head))
        <div @class(['flex', 'cursor-pointer items-center' => $klappbar, 'items-start' => ! $klappbar]) @if ($klappbar) x-on:click="kopfKlick($event)" @endif>
            @if ($klappbar)
                <button type="button" x-on:click="umschalten()" x-bind:aria-expanded="offen ? 'true' : 'false'"
                    aria-label="{{ __('Details ein- oder ausklappen') }}"
                    class="ml-3 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-chathams-blue-800 focus:outline-hidden focus:ring-2 focus:ring-cerulean-500 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-100">
                    <svg x-bind:class="offen ? 'rotate-90' : ''" class="h-3.5 w-3.5 transition-transform" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </button>
            @endif
            <div class="min-w-0 flex-1">
                {{ $head }}
            </div>
        </div>
    @endif
    <div data-karte-inhalt @if ($klappbar) x-show="offen" x-cloak @endif @class([
        'border-t border-gray-100 dark:border-gray-700' => trim($head) !== '',
        'flex flex-wrap gap-x-8 gap-y-5 p-5' => $plain,
        {{-- Der mb-5 des letzten Blocks zaehlt am Spaltenende nicht mit - die
             Dienste-Kacheln klebten am Kartenrand. Der Abstand kommt deshalb aus
             dem Padding und ist unten so gross wie oben. --}}
        {{-- Zwei Spalten ab md: Die Seitenleiste klappt erst ab lg auf, unter
             1024px steht die ganze Breite zur Verfuegung. Damit ist eine Spalte
             bei 768px rund 340px breit - genauso viel wie bei 1024px mit
             Seitenleiste. Vorher erschien die Leiste schon ab 640px, dort
             blieben je gut 200px, und darin passte "Seriennummer CZ29470H8K-..."
             nicht: Die Tabelle lief in die Nachbarspalte und aus der Karte. --}}
        'p-5 columns-1 md:columns-2 xl:columns-3 gap-x-10' => ! $plain && ! $raster,
        // raster: fixed columns by theme (x-kartenspalte) instead of blocks
        // flowing by height - every block in the same place on every card.
        'p-5 grid grid-cols-1 gap-x-8 gap-y-6 lg:grid-cols-2' => $raster === 2,
        'p-5 grid grid-cols-1 gap-x-8 gap-y-6 lg:grid-cols-3' => $raster === 3,
        'lg:pl-14' => $klappbar,
    ])>
        {{ $body }}
    </div>
    {{-- lg:pl-14 above: the content indented to the name of the head line
         - arrow 12 + 32 px, head padding 12 px - reads as belonging to it.
         Optional foot under the columns (x-kartenfuss): last change, agent,
         links to the activity log, server map and rack. --}}
    @isset($fuss)
        <div @if ($klappbar) x-show="offen" x-cloak @endif>
            {{ $fuss }}
        </div>
    @endisset
</x-panel>
</div>
