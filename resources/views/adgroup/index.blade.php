<x-app-layout :$customer>

    <x-sitetopmenu can="adgroup_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Gruppenname', 'Beschreibung', '', ]" />

            <x-table.body>

                @forelse ($customer->adgroups as $adgroup)

                    <x-table.datarow
                        :values="[
                            $adgroup->name,
                            $adgroup->description,
                        ]"

                        editUrl="{{ route('adgroup.edit', [$customer, $adgroup]) }}"
                        can="adgroup_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">Noch keine Einträge vorhanden.</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>

