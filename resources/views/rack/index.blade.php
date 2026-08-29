<x-app-layout :$customer>

    <x-sitetopmenu can="rack_create" />

    @forelse ($racks as $rack)
        <x-card plain>
            <x-slot:head>
                <x-show.header can="rack_update" editUrl="{{ route('rack.edit', [$customer, $rack]) }}">
                    {{ $rack->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                {{-- Die Eckdaten in einer Zeile statt als schmale Tabellenspalte:
                     Es sind vier kurze Angaben, und darunter brauchen die
                     Zeichnungen die volle Breite. --}}
                <dl class="w-full flex flex-wrap gap-x-10 gap-y-3 text-sm">
                    @foreach ([
                        'Standort' => $customer->sites->firstWhere('id', $rack->site_id)?->name,
                        'Ort' => $rack->location,
                        'Höheneinheiten' => $rack->height_units.' HE',
                        'Einbauten' => $rack->items->where('side', 'front')->count()
                            .($rack->items->where('side', 'rear')->isNotEmpty()
                                ? ' + '.$rack->items->where('side', 'rear')->count().' '.__('hinten')
                                : ''),
                        'Notiz' => $rack->note,
                    ] as $bezeichnung => $wert)
                        @if (filled($wert))
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __($bezeichnung) }}</dt>
                                <dd class="dark:text-gray-100">{{ $wert }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                {{-- Umschalter wie im Editor, nur ohne Server-Runde: Beide Seiten
                     stehen im Markup, Alpine blendet um. Untereinander zu stapeln
                     hiesse, dass man für die Rückseite scrollen muss – bei 42 HE
                     ist die Vorderseite einen Bildschirm hoch. --}}
                <div class="w-full" x-data="{ seite: 'front' }">

                    <div class="mb-3 inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-600" role="tablist">
                        @foreach (\App\Models\Rack::SEITEN as $wert => $bezeichnung)
                            <button type="button" x-on:click="seite = '{{ $wert }}'" role="tab"
                                :aria-selected="seite === '{{ $wert }}'"
                                class="rounded-md px-3 py-1.5 text-sm transition-colors"
                                :class="seite === '{{ $wert }}'
                                    ? 'bg-cerulean-500 text-white'
                                    : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'">
                                {{ __($bezeichnung) }}
                                <span class="ml-1 text-[10px] font-mono opacity-70">{{ $rack->items->where('side', $wert)->count() }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach (\App\Models\Rack::SEITEN as $wert => $bezeichnung)
                        <div x-show="seite === '{{ $wert }}'" x-cloak class="flex flex-wrap gap-x-8 gap-y-5">
                            <div class="w-full sm:w-80">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">{{ __('Belegung') }}</div>
                                @include('rack._grid', ['rack' => $rack, 'interactive' => false, 'seite' => $wert])
                            </div>

                            {{-- Breiter als das Schema daneben: Die Zeichnung braucht
                                 das 10,857-fache ihrer Zeilenhoehe, sonst stauchen
                                 sich die Blenden (siehe rack/_rackview). --}}
                            <div class="w-full sm:w-[27rem]">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                                    {{ $wert === 'front' ? __('Frontansicht') : __('Rückansicht') }}
                                </div>
                                @include('rack._rackview', ['rack' => $rack, 'seite' => $wert])
                            </div>
                        </div>
                    @endforeach
                </div>

            </x-slot>
        </x-card>
    @empty
        <x-emptystate message="Noch keine Serverschränke dokumentiert." />
    @endforelse

    <div class="px-3 pb-3">
        {{ $racks->links() }}
    </div>

</x-app-layout>
