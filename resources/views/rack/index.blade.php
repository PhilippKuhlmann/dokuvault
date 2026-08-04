<x-app-layout :$customer>

    <x-sitetopmenu can="rack_create" />

    @forelse ($racks as $rack)
        <x-card>
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

                {{-- Zeichnungen darunter, nebeneinander solange Platz ist. --}}
                <div class="w-full flex flex-wrap gap-x-8 gap-y-5">
                    <div class="w-full sm:w-80">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">{{ __('Belegung') }}</div>
                        @include('rack._grid', ['rack' => $rack, 'interactive' => false, 'seite' => 'front'])
                    </div>

                    <div class="w-full sm:w-80">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">{{ __('Frontansicht') }}</div>
                        @include('rack._rackview', ['rack' => $rack, 'seite' => 'front'])
                    </div>

                    {{-- Die Rueckseite nur zeigen, wenn dort etwas steht. Sonst
                         naehme eine leere Zeichnung Platz weg, ohne etwas zu sagen. --}}
                    @if ($rack->items->where('side', 'rear')->isNotEmpty())
                        <div class="w-full sm:w-80">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">{{ __('Rückansicht') }}</div>
                            @include('rack._rackview', ['rack' => $rack, 'seite' => 'rear'])
                        </div>
                    @endif
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
