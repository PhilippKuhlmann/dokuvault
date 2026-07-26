<x-app-layout :$customer>

    <x-sitetopmenu can="addomain_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Domäne', 'NETBIOS', 'DSRM Passwort', '', ]" />

            <x-table.body>

                @forelse ($customer->addomains as $addomain)

                    <x-table.datarow
                        :values="[
                            $addomain->domain,
                            $addomain->netbios,
                            'password' => $addomain->dsrmpassword,
                        ]"

                        editUrl="{{ route('addomain.edit', [$customer, $addomain]) }}"
                        can="addomain_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>
