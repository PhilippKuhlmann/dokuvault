<x-app-layout :$customer>

    <x-sitetopmenu can="loginnas_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['NAS', 'Benutzername', 'Passwort', 'Beschreibung', '' ]" />

            <x-table.body>

                @forelse ($customer->loginnas as $loginnas)

                    <x-table.datarow
                        :values="[
                            $loginnas->nas?->name ?? '—',
                            $loginnas->username,
                            'password' => $loginnas->password,
                            $loginnas->description,
                        ]"

                        editUrl="{{ route('loginnas.edit', [$customer, $loginnas]) }}"
                        can="loginnas_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>
