<x-app-layout :$customer>

    <x-sitetopmenu can="patchpanel_create" />

    @forelse ($patchpanels as $patchpanel)
        @php
            $belegt = $patchpanel->ports->filter(fn ($p) => $p->isDocumented());
        @endphp
        <x-card>
            <x-slot:head>
                <x-show.header can="patchpanel_update" editUrl="{{ route('patchpanel.edit', [$customer, $patchpanel]) }}">
                    {{ $patchpanel->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard title="Allgemein" :array="[
                    'Standort' => $customer->sites->firstWhere('id', $patchpanel->site_id)?->name,
                    'Hersteller' => $patchpanel->manufacturer,
                    'Modell' => $patchpanel->model,
                    'Höheneinheiten' => $patchpanel->height_units . ' HE',
                ]" />

                <x-minitablecard title="Belegung" :array="[
                    'Ports' => $patchpanel->port_count,
                    'Dokumentiert' => $belegt->count(),
                    'Frei' => $patchpanel->port_count - $belegt->count(),
                ]" />

                @if ($belegt->isNotEmpty())
                    <div class="w-full">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Dosen</div>
                        <table class="w-full text-sm">
                            <thead class="text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="py-2 pr-4 text-left font-semibold">Port</th>
                                    <th class="py-2 pr-4 text-left font-semibold">Dose</th>
                                    <th class="py-2 pr-4 text-left font-semibold">Raum</th>
                                    <th class="py-2 pr-4 text-left font-semibold">Switch</th>
                                    <th class="py-2 pr-4 text-left font-semibold">Switch-Port</th>
                                    <th class="py-2 text-left font-semibold">Notiz</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($belegt as $port)
                                    <tr class="border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                                        <td class="py-2 pr-4 font-mono text-gray-900 dark:text-gray-100">{{ $port->number }}</td>
                                        <td class="py-2 pr-4 font-mono text-gray-900 dark:text-gray-100">{{ $port->outlet ?: '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-900 dark:text-gray-100">{{ $port->label ?: '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">{{ $port->networkSwitch?->name ?: '—' }}</td>
                                        <td class="py-2 pr-4 font-mono text-gray-600 dark:text-gray-300">{{ $port->switch_port ?: '—' }}</td>
                                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ $port->note ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </x-slot>
        </x-card>
    @empty
        <x-emptystate message="Noch keine Patchfelder dokumentiert." />
    @endforelse

    <div class="px-3 pb-3">
        {{ $patchpanels->links() }}
    </div>

</x-app-layout>
