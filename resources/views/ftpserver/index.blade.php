<x-app-layout :$customer>

    <x-sitetopmenu can="ftpserver_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Host', 'Benutzername', 'Passwort', 'Beschreibung', '', ]" />

            <x-table.body>

                @forelse ($customer->ftpservers as $ftpserver)

                    <x-table.datarow
                        :values="[
                            $ftpserver->host,
                            $ftpserver->username,
                            'password' => $ftpserver->password,
                            $ftpserver->description,
                        ]"

                        editUrl="{{ route('ftpserver.edit', [$customer, $ftpserver]) }}"
                        can="ftpserver_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>
