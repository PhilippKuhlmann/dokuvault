<x-app-layout :$customer>

    <x-sitetopmenu can="machine_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'IP', 'Zugangsdaten', '', ]" />

            <x-table.body>

                @forelse ($machines as $machine)

                    @php
                        // $adressen wurde hier nie gesetzt - die Zeile stammt aus
                        // den Listen mit Kartenansicht, wo sie oberhalb der
                        // Schleife steht. Die Seite antwortete deshalb mit 500,
                        // sobald ueberhaupt eine Maschine da war.
                        $adressen = $machine->relationLoaded('ipAddresses')
                            ? $machine->ipAddresses
                            : $machine->ipAddresses()->get();
                        $primaer = $adressen->first()?->address;
                    @endphp

                    <x-table.datarow
                        :values="[
                            $machine->name,
                            $primaer,
                            'credentials' => $machine,
                        ]"

                        editUrl="{{ route('machine.edit', [$customer, $machine]) }}"
                        can="machine_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

    <div class="px-3 pb-3">
        {{ $machines->links() }}
    </div>

</x-app-layout>
