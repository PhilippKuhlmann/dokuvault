{{--
    Die Rustdesk-Suche im selben Blatt wie die beiden anderen Suchen:
    Millimeterpapier, Schriftkopf, Versalien auf Monospace, scharfe Ecken.

    Sie war am weitesten zurueck - eigene Tabelle mit dunkelblauem Kopf, der im
    Dunkelmodus invertierte (heller Balken auf dunkler Seite), und ein Suchfeld
    mit dark:bg-gray-200, also hellgrau im Dunkeln. Beides faellt mit dem
    Umbau weg: Das Feld ist jetzt x-input.text wie ueberall sonst.

    Die Treffer stehen im Muster der globalen Suche - Geraetename in Versalien,
    Kunde rechts in Monospace. Der Verbinden-Knopf sitzt am Zeilenende, und
    deshalb ist die Zeile hier kein Link: Ein Knopf in einem Link waere ein
    Klickziel in einem anderen.
--}}
<div class="relative min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-12
            bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
            dark:from-gray-900 dark:to-cerulean-950">

    <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                text-chathams-blue-100 dark:text-cerulean-950"></div>

    <div class="relative z-10 mx-auto w-full max-w-2xl rounded-lg border border-chathams-blue-200
                bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">

        <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                    font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                    dark:border-gray-700 dark:text-gray-400">
            <span>{{ __('Rustdesk-Suche') }}</span>
            @if ($search)
                {{-- Zwei Zeichenketten statt trans_choice: siehe
                     TwoFactorChallengeController. --}}
                <span>{{ count($remotes) === 1 ? __('1 Treffer') : __(':anzahl Treffer', ['anzahl' => count($remotes)]) }}</span>
            @endif
        </div>

        <div class="px-6 py-6">
            <x-input.feldname for="rustdesksuche" :value="__('Suchbegriff')" />

            <div class="relative mt-1.5">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"
                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                    </svg>
                </span>

                <x-input.text id="rustdesksuche" wire:model.live.debounce.300ms="search" type="search" name="search"
                    class="block w-full pl-10" placeholder="{{ __('Kunde oder Gerät suchen …') }}" autofocus />
            </div>

            <p class="mt-2 font-mono text-[11px] uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">
                {{ __('Kunde oder Gerät — gelistet wird, was eine Rustdesk-Kennung hat') }}
            </p>
        </div>

        <div class="border-t border-chathams-blue-100 dark:border-gray-700">

            @if (! $search)
                <p class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Namen eintippen — die Liste folgt beim Schreiben.') }}
                </p>
            @elseif (! count($remotes))
                <p class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Kein Treffer.') }}
                </p>
            @else
                <ul class="max-h-[32rem] divide-y divide-chathams-blue-100 overflow-auto dark:divide-gray-700">
                    @foreach ($remotes as $remote)
                        <li class="flex items-center justify-between gap-4 px-6 py-3">
                            <span class="min-w-0">
                                <span class="block truncate uppercase tracking-[0.06em] text-gray-900 dark:text-gray-100">
                                    {{ $remote['name'] }}
                                </span>
                                <span class="block truncate font-mono text-xs text-gray-400 dark:text-gray-500">
                                    {{ $remote['customerName'] }}
                                </span>
                            </span>

                            <span class="shrink-0">
                                <x-remote.button :id="$remote['remoteID']" :password="$remote['remotePassword']" stil="label" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

        </div>
    </div>
</div>
