{{--
    Die Kundensuche im Aussehen der Anmeldeseite: Millimeterpapier,
    Schriftkopf, Versalien auf Monospace, scharfe Ecken.

    Sie trug bis eben die alte Huelle - Verlaufs-Badge, rounded-2xl, eigener
    Farbverlauf. Das ist die erste Seite nach der Anmeldung; sprang sie dort
    ins alte Aussehen zurueck, war der Umbau der Tuer davor vergeblich.

    Bewusst ohne Netzplan dahinter: Auf der Anmeldung sagt er etwas - das
    steckt hinter der Tuer. Hier waere er dieselbe Zeichnung ein zweites Mal,
    und ueber der Karte steht ohnehin schon die Navigationsleiste.
--}}
<div class="relative min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-12
            bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
            dark:from-gray-900 dark:to-cerulean-950">

    <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                text-chathams-blue-100 dark:text-cerulean-950"></div>

    {{-- Keyboard navigation: arrow keys move the active result, Enter opens it.
         The active index lives on the persistent Alpine root (survives Livewire
         re-renders) and resets to the first hit when the query changes. --}}
    <div x-data="{
            active: 0,
            items() { return [...$root.querySelectorAll('[data-result]')] },
            move(step) {
                const list = this.items()
                if (! list.length) return
                this.active = Math.max(0, Math.min(this.active + step, list.length - 1))
                list[this.active]?.scrollIntoView({ block: 'nearest' })
            },
            choose() { this.items()[this.active]?.click() },
        }"
        class="relative z-10 mx-auto w-full max-w-2xl rounded-lg border border-chathams-blue-200
                bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">

        {{-- Schriftkopf wie auf einer technischen Zeichnung: links, was das
             Blatt ist, rechts die Stueckzahl. --}}
        <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                    font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                    dark:border-gray-700 dark:text-gray-400">
            <span>{{ __('Kundensuche') }}</span>
            {{-- Zwei Zeichenketten statt trans_choice: Bei einer Pluralform,
                 die auf Deutsch nicht hinterlegt ist, faellt Laravel auf die
                 Ersatzsprache zurueck - siehe TwoFactorChallengeController. --}}
            <span>{{ $gesamt === 1 ? __('1 Kunde') : __(':anzahl Kunden', ['anzahl' => $gesamt]) }}</span>
        </div>

        <div class="px-6 py-6">
            <x-input.feldname for="kundensuche" :value="__('Suchbegriff')" />

            <div class="relative mt-1.5">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"
                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                    </svg>
                </span>

                <x-input.text id="kundensuche" wire:model.live.debounce.300ms="search" type="search" name="search"
                    class="block w-full pl-10" placeholder="{{ __('Kunde suchen …') }}" autofocus autocomplete="off"
                    data-1p-ignore data-lpignore="true" data-bwignore data-form-type="other"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose()"
                    x-on:keydown.escape.prevent="window.history.length > 1 ? window.history.back() : window.location.assign('/')"
                    x-on:input="active = 0" />
            </div>

            {{-- Tastaturhinweis: dieselbe Steuerung wie in der globalen Suche. --}}
            <p class="mt-2 font-mono text-[11px] uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">
                {{ __('↑↓ wählen · ↵ öffnen · Esc schließen') }}
            </p>
        </div>

        <div class="border-t border-chathams-blue-100 dark:border-gray-700">

            @if (! $search)
                {{-- Vor der ersten Eingabe steht hier kein "keine Treffer":
                     Es wurde ja noch nichts gesucht. --}}
                <p class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Namen eintippen — die Liste folgt beim Schreiben.') }}
                </p>
            @elseif (! $customers)
                <p class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Kein Kunde mit diesem Namen.') }}
                </p>
            @else
                <ul class="max-h-96 divide-y divide-chathams-blue-100 overflow-auto dark:divide-gray-700">
                    @foreach ($customers as $index => $customer)
                        <li>
                            <a href="/{{ $customer->slug }}"
                                data-result
                                x-on:mouseenter="active = {{ $index }}"
                                x-bind:class="active === {{ $index }} && 'bg-chathams-blue-50 ring-2 ring-inset ring-cerulean-500 dark:bg-gray-700/50'"
                                class="flex items-center justify-between gap-3 px-6 py-3 transition-colors
                                       hover:bg-chathams-blue-50 focus:outline-hidden focus:ring-2
                                       focus:ring-cerulean-500 dark:hover:bg-gray-700/50">
                                <span class="min-w-0">
                                    {{-- Versalien wie die Beschriftungen im Plan. Der Ort
                                         dahinter bleibt klein und gedeckt: Er unterscheidet
                                         zwei gleich heissende Kunden, ist aber nicht das,
                                         wonach jemand sucht. --}}
                                    <span class="block truncate uppercase tracking-[0.06em] text-gray-900 dark:text-gray-100">
                                        {{ $customer->name }}
                                    </span>
                                    @if ($customer->location)
                                        <span class="block truncate font-mono text-xs text-gray-400 dark:text-gray-500">
                                            {{ $customer->location }}
                                        </span>
                                    @endif
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2" xmlns="http://www.w3.org/2000/svg"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- Die Liste zeigt hoechstens fuenfzig Treffer. Ohne diesen
                     Hinweis sieht es aus, als gaebe es keine weiteren - und
                     jemand sucht den Kunden, der knapp nicht mehr dabei ist. --}}
                @if ($weitere > 0)
                    <p class="border-t border-chathams-blue-100 px-6 py-3 font-mono text-[11px] uppercase
                              tracking-[0.12em] text-gray-400 dark:border-gray-700 dark:text-gray-500">
                        {{ __('Weitere Treffer vorhanden — Suchbegriff verfeinern.') }}
                    </p>
                @endif
            @endif

        </div>
    </div>
</div>
