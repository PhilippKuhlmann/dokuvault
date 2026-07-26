<x-app-layout :$customer>

    <x-sitetopmenu can="loginwebsite_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'Benutzername', 'Passwort', 'URL', '', ]" />

            <x-table.body>

                @forelse ($customer->loginwebsites as $loginwebsite)

                    <x-table.datarow
                        :values="[
                            $loginwebsite->name,
                            $loginwebsite->username,
                            'password' => $loginwebsite->password,
                            'url' => $loginwebsite->url,
                        ]"

                        editUrl="{{ route('loginwebsite.edit', [$customer, $loginwebsite]) }}"
                        can="loginwebsite_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>
