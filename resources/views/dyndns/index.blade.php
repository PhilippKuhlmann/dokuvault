<x-app-layout :$customer>

    <x-sitetopmenu can="dyndns_create" />


    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Domain', 'Anbieter', 'Host', 'Benutzername', 'Passwort', '', ]" />

            <x-table.body>

                @forelse ($customer->dyndns as $dyndns)

                    <x-table.datarow
                        :values="[
                            $dyndns->domain,
                            $dyndns->providor,
                            $dyndns->host,
                            $dyndns->username,
                            'password' => $dyndns->password,
                        ]"

                        editUrl="{{ route('dyndns.edit', [$customer, $dyndns]) }}"
                        can="dyndns_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>
