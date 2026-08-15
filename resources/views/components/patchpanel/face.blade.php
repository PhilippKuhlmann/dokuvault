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

    {{-- Der Rahmen ist die Blende: dunkles Metall, Buchsen darin. Bei 24 Ports
         wird sie breiter als eine Kartenspalte, deshalb ein eigener Scrollbereich. --}}
    <div class="overflow-x-auto">
    <div class="inline-block rounded border border-gray-300 bg-gray-200 p-2 dark:border-gray-600 dark:bg-gray-900">
        @foreach ($reihen as $reihe)
            <div class="flex gap-1 {{ ! $loop->first ? 'mt-1' : '' }}">
                @foreach ($reihe as $nummer)
                    @php
                        $port = $ports[$nummer] ?? null;
                        $istBelegt = $port?->isDocumented();
                        $titel = $istBelegt
                            ? collect([
                                __('Port').' '.$nummer,
                                $port->outlet ? __('Dose').' '.$port->outlet : null,
                                $port->label,
                                $port->networkSwitch?->name
                                    ? $port->networkSwitch->name.($port->switch_port ? ' · '.__('Port').' '.$port->switch_port : '')
                                    : null,
                            ])->filter()->implode(' · ')
                            : __('Port').' '.$nummer.' · '.__('frei');
                    @endphp

                    <div title="{{ $titel }}"
                        @class([
                            'flex h-6 w-6 items-center justify-center rounded-[2px] border font-mono text-[10px] tabular-nums',
                            'ml-2' => $loop->iteration % 6 === 1 && ! $loop->first,
                            'border-cerulean-700 bg-cerulean-500 text-white' => $istBelegt,
                            'border-gray-300 bg-white text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500' => ! $istBelegt,
                        ])>
                        {{ $nummer }}
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
