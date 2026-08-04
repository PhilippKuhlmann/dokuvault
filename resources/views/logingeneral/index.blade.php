<x-app-layout :$customer>

    <x-sitetopmenu can="logingeneral_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'Benutzername', 'Passwort', 'Beschreibung', '', ]" />

            <x-table.body>

                @forelse ($customer->logingenerals as $logingeneral)

                    <x-table.datarow
                        :values="[
                            $logingeneral->name,
                            $logingeneral->username,
                            'password' => $logingeneral->password,
                            $logingeneral->description,

                        ]"

                        editUrl="{{ route('logingeneral.edit', [$customer, $logingeneral]) }}"
                        can="logingeneral_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>
