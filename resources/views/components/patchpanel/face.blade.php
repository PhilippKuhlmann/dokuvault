@props(['panel'])

@php
    // Eine Reihe je Hoeheneinheit, so wie das Geraet gebaut ist: 24 Ports auf
    // 1 HE stehen nebeneinander, ein 48er auf 2 HE bekommt zwei Reihen zu 24.
    // Die Luecke nach je sechs Buchsen gibt es am echten Feld auch - damit
    // findet man Port 19 durch Abzaehlen der Bloecke statt der Buchsen.
    $ports = $panel->ports->keyBy('number');
    $proReihe = (int) ceil(max($panel->port_count, 1) / max($panel->height_units, 1));
    $reihen = collect(range(1, max($panel->port_count, 1)))->chunk($proReihe);
    $belegt = $panel->ports->filter(fn ($p) => $p->isDocumented())->count();
@endphp

<div class="w-full mb-5 break-inside-avoid">
    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ __('Frontansicht') }}
    </div>

    {{-- Eigener Scrollbereich: 24 Buchsen sind breiter als eine Kartenspalte.
         overflow-visible in der Senkrechten, sonst schneidet der Scrollrahmen
         das Hover-Fenster ab. --}}
    <div class="overflow-x-auto pb-1">
    <div class="inline-block rounded border border-gray-300 bg-gray-200 p-2 dark:border-gray-600 dark:bg-gray-900">
        @foreach ($reihen as $reihe)
            <div class="flex gap-1 {{ ! $loop->first ? 'mt-1' : '' }}">
                @foreach ($reihe as $nummer)
                    @php
                        $port = $ports[$nummer] ?? null;
                        $istBelegt = $port?->isDocumented();
                    @endphp

                    <div @class(['relative', 'ml-2' => $loop->iteration % 6 === 1 && ! $loop->first])
                        @if ($istBelegt) x-data="{ offen: false }" x-on:mouseenter="offen = true" x-on:mouseleave="offen = false" @endif>

                        <div @class([
                                'flex h-6 w-6 items-center justify-center rounded-[2px] border font-mono text-[10px] tabular-nums',
                                'cursor-help border-cerulean-700 bg-cerulean-500 text-white' => $istBelegt,
                                'border-gray-300 bg-white text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500' => ! $istBelegt,
                            ])>
                            {{ $nummer }}
                        </div>

                        @if ($istBelegt)
                            {{-- Das Hover-Fenster steht ueber der Buchse, damit es in der
                                 untersten Reihe nicht aus der Karte laeuft. --}}
                            <div x-show="offen" x-cloak
                                class="absolute bottom-full left-1/2 z-20 mb-1 w-56 -translate-x-1/2 rounded border border-gray-200 bg-white p-2.5 text-left shadow-lg dark:border-gray-600 dark:bg-gray-800">
                                <div class="mb-1 font-mono text-xs font-semibold text-cerulean-700 dark:text-cerulean-400">
                                    {{ __('Port') }} {{ $nummer }}
                                </div>
                                <table class="w-full text-xs">
                                    @foreach ([
                                        'Dose' => $port->outlet,
                                        'Raum' => $port->label,
                                        'Switch' => $port->networkSwitch?->name,
                                        'Switch-Port' => $port->switch_port,
                                        'Notiz' => $port->note,
                                    ] as $bezeichnung => $wert)
                                        @if (filled($wert))
                                            <tr>
                                                <td class="py-0.5 pr-3 align-top whitespace-nowrap text-gray-500 dark:text-gray-400">{{ __($bezeichnung) }}</td>
                                                <td class="py-0.5 w-full text-gray-900 dark:text-gray-100">{{ $wert }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-[2px] border border-cerulean-700 bg-cerulean-500"></span>
            {{ $belegt }} {{ __('dokumentiert') }}
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-[2px] border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800"></span>
            {{ $panel->port_count - $belegt }} {{ __('frei') }}
        </span>
    </div>
</div>
