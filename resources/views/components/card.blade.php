@props(['plain' => false])

{{--
    Der Inhalt läuft in Spalten (CSS multi-column) statt in einer Flex-Reihe:
    Die Blöcke sind unterschiedlich hoch - Hardware hat sieben Zeilen,
    Zugangsdaten oft eine - und eine Flex-Reihe zieht alle auf die Höhe des
    längsten. Darunter klaffte dann Leere. In Spalten rutscht jeder Block
    direkt unter seinen Vorgänger.

    plain: für Karten, die ihren Inhalt selbst anordnen (Serverschränke).
--}}
<div class="flex flex-col m-3 rounded-xl border border-gray-200 bg-white shadow-sm dark:text-gray-100 dark:bg-gray-800 dark:border-gray-700">
    @if (trim($head))
        <div class="border-b border-gray-100 dark:border-gray-700">
            {{ $head }}
        </div>
    @endif
    <div @class([
        'flex flex-wrap gap-x-8 gap-y-5 p-5' => $plain,
        {{-- Der mb-5 des letzten Blocks zaehlt am Spaltenende nicht mit - die
             Dienste-Kacheln klebten am Kartenrand. Der Abstand kommt deshalb aus
             dem Padding und ist unten so gross wie oben. --}}
        'p-5 columns-1 md:columns-2 xl:columns-3 gap-x-10' => ! $plain,
    ])>
        {{ $body }}
    </div>
</div>
