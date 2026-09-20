<x-empty-layout>

    {{--
        Der Changelog im selben Blatt wie die Suchen: Millimeterpapier,
        Schriftkopf, scharfe Ecken.

        Vorher stand hier ein nackter Markdown-Block ohne Karte - und die Seite
        haengt an der Versionsnummer im Schriftkopf des Anmeldeblatts. Wer dort
        klickt, landete in etwas, das nach einer halb geladenen Seite aussah.

        Die Farben des Markdowns stehen in app.css unter ".markdown": Sie
        lassen sich nicht als Utility-Klassen setzen, weil das Markup hier
        erst zur Laufzeit aus der Datei entsteht.
    --}}
    <div class="relative min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-12
                bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
                dark:from-gray-900 dark:to-cerulean-950">

        <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                    text-chathams-blue-100 dark:text-cerulean-950"></div>

        <div class="relative z-10 mx-auto w-full max-w-3xl rounded-lg border border-chathams-blue-200
                    bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">

            <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                        font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                        dark:border-gray-700 dark:text-gray-400">
                <span>{{ __('Changelog') }}</span>
                <span class="normal-case">v{{ $version }}</span>
            </div>

            <div class="markdown px-6 py-8">
                {{ Illuminate\Mail\Markdown::parse($changelog) }}
            </div>
        </div>
    </div>

</x-empty-layout>
