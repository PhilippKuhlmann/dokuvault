{{--
    Die globale Suche im Aussehen der Anmeldeseite, wie die Kundensuche
    daneben: Millimeterpapier, Schriftkopf, Versalien auf Monospace, scharfe
    Ecken. Auch ohne Netzplan - der sagt auf der Anmeldung etwas, hier waere er
    dieselbe Zeichnung ein zweites Mal.

    Die Treffer stehen nach Objektart gruppiert. Die Art ist die
    Abschnittsueberschrift, wie eine Baugruppe auf einer Zeichnung; rechts an
    der Zeile steht der Kunde, denn dieselbe IP gibt es in jedem Netz einmal.
--}}
@php
    $gezeigt = $groups->sum(fn ($gruppe) => $gruppe['results']->count());
    $gekuerzt = $groups->contains(fn ($gruppe) => ($gruppe['weitere'] ?? 0) > 0);
    $gesucht = strlen((string) $search) >= 2;
@endphp

<div class="relative min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-12
            bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
            dark:from-gray-900 dark:to-cerulean-950">

    <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                text-chathams-blue-100 dark:text-cerulean-950"></div>

    {{-- Keyboard navigation: arrow keys move the active result across all
         groups (flat DOM order via [data-result]), Enter opens it. The active
         index survives Livewire re-renders on the persistent Alpine root and is
         reset to the first hit whenever the query changes (x-on:input). --}}
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

        <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                    font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                    dark:border-gray-700 dark:text-gray-400">
            <span>{{ __('Globale Suche') }}</span>
            @if ($gesucht)
                {{-- Zwei Zeichenketten statt trans_choice: siehe
                     TwoFactorChallengeController. --}}
                <span>{{ $gezeigt === 1 ? __('1 Treffer') : __(':anzahl Treffer', ['anzahl' => $gezeigt]) }}</span>
            @endif
        </div>

        <div class="px-6 py-6">
            <x-input.feldname for="globalesuche" :value="__('Suchbegriff')" />

            <div class="relative mt-1.5">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"
                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                    </svg>
                </span>

                <x-input.text id="globalesuche" wire:model.live.debounce.300ms="search" type="search" name="search"
                    class="block w-full pl-10" placeholder="{{ __('z. B. 192.168.1.50, PC-07, Seriennummer …') }}"
                    autofocus
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose()"
                    x-on:keydown.escape.prevent="window.history.length > 1 ? window.history.back() : window.location.assign('/')"
                    x-on:input="active = 0" />
            </div>

            <p class="mt-2 font-mono text-[11px] uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">
                {{ __('Name, IP, Seriennummer oder MAC über alle Geräte') }}
            </p>

            {{-- Tastaturhinweis: dieselbe Steuerung wie in der Befehlspalette. --}}
            <p class="mt-1 font-mono text-[11px] uppercase tracking-[0.12em] text-gray-400 dark:text-gray-500">
                {{ __('↑↓ wählen · ↵ öffnen · Esc schließen') }}
            </p>
        </div>

        <div class="border-t border-chathams-blue-100 dark:border-gray-700">

            @if (! $gesucht)
                {{-- Unter zwei Zeichen wird nicht gesucht. Das gehoert
                     hingeschrieben: Sonst sieht ein einzelner Buchstabe ohne
                     Liste aus wie "nichts gefunden". --}}
                <p class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Mindestens zwei Zeichen eintippen.') }}
                </p>
            @else
                @php($i = 0)
                @forelse ($groups as $gruppe)
                    <div class="border-b border-chathams-blue-100 last:border-0 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-4 bg-chathams-blue-50/60 px-6 py-2
                                    font-mono text-[11px] uppercase tracking-[0.12em] text-gray-500
                                    dark:bg-gray-700/40 dark:text-gray-400">
                            <span>{{ $gruppe['label'] }}</span>
                            @if (($gruppe['weitere'] ?? 0) > 0)
                                <span>{{ __('weitere vorhanden') }}</span>
                            @endif
                        </div>

                        <ul class="divide-y divide-chathams-blue-100 dark:divide-gray-700">
                            @foreach ($gruppe['results'] as $treffer)
                                @php($idx = $i++)
                                <li>
                                    <a href="{{ route($gruppe['slug'] . '.index', ($gruppe['highlightable'] ?? false) ? [$treffer->customer, 'highlight' => $treffer->id] : [$treffer->customer]) }}"
                                        data-result
                                        x-on:mouseenter="active = {{ $idx }}"
                                        x-bind:class="active === {{ $idx }} && 'bg-chathams-blue-50 ring-2 ring-inset ring-cerulean-500 dark:bg-gray-700/50'"
                                        class="flex items-center justify-between gap-4 px-6 py-3 transition-colors
                                               hover:bg-chathams-blue-50 focus:outline-hidden focus:ring-2
                                               focus:ring-cerulean-500 dark:hover:bg-gray-700/50">
                                        <span class="min-w-0">
                                            {{-- Dieselbe Quelle wie Protokoll und Papierkorb:
                                                 config('custom.name_fields'), ueberschreibbar je Model.
                                                 Vorher stand hier eine eigene Kette (name, ssid,
                                                 wan_ip) - ein Telefon hat keines davon und hiess
                                                 deshalb "#14", ausgerechnet in der Suche nach
                                                 seiner MAC. --}}
                                            <span class="block truncate uppercase tracking-[0.06em] text-gray-900 dark:text-gray-100">
                                                {{ $treffer->protokollName() ?? '#'.$treffer->id }}
                                            </span>
                                            @if ($adresse = $treffer->ip ?? $treffer->ip1 ?? null)
                                                <span class="block truncate font-mono text-xs text-gray-400 dark:text-gray-500">
                                                    {{ $adresse }}
                                                </span>
                                            @endif
                                        </span>

                                        {{-- Der Kunde gehoert an die Zeile: Dieselbe IP gibt es in
                                             jedem Netz einmal, und ohne ihn weiss man nicht, wessen
                                             Geraet man gerade anklickt. --}}
                                        <span class="shrink-0 truncate font-mono text-xs uppercase tracking-[0.08em]
                                                     text-gray-400 dark:text-gray-500">
                                            {{ $treffer->customer?->name }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ __('Kein Treffer.') }}
                    </p>
                @endforelse

                @if ($gekuerzt)
                    <p class="border-t border-chathams-blue-100 px-6 py-3 font-mono text-[11px] uppercase
                              tracking-[0.12em] text-gray-400 dark:border-gray-700 dark:text-gray-500">
                        {{ __('Je Objektart höchstens zwanzig gezeigt — Suchbegriff verfeinern.') }}
                    </p>
                @endif
            @endif

        </div>
    </div>
</div>
