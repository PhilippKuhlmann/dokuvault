<x-app-layout :$customer>

    <x-sitetopmenu can="wifi_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['SSID', 'Netzwerk', 'Verschlüsselung', 'Passwort', '', ]" />

            <x-table.body>

                @forelse ($wifis as $wifi)

                    <x-table.datarow
                        :values="[
                            $wifi->ssid,
                            $wifi->network ? ($wifi->network->vlanId . ' - ' . $wifi->network->description) : '—',
                            $wifi->encryption,
                            'password' => $wifi->password,
                        ]"

                        editUrl="{{ route('wifi.edit', [$customer, $wifi]) }}"
                        can="wifi_update"

                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

    <div class="px-3 pb-3">
        {{ $wifis->links() }}
    </div>

</x-app-layout>

