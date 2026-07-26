<x-app-layout :$customer>

    <x-sitetopmenu can="loginrecorder_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Recorder', 'Benutzername', 'Passwort', '' ]" />

            <x-table.body>

                @forelse ($loginrecorders as $loginrecorder)

                    <x-table.datarow
                        :values="[
                            $loginrecorder->recorder?->name ?? '—',
                            $loginrecorder->username,
                            'password' => $loginrecorder->password,
                        ]"

                        editUrl="{{ route('loginrecorder.edit', [$customer, $loginrecorder]) }}"
                        can="loginrecorder_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

    <div class="px-3 pb-3">
        {{ $loginrecorders->links() }}
    </div>

</x-app-layout>
