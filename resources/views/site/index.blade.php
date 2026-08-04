<x-app-layout :$customer>

    <x-sitetopmenu can="site_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'Straße', 'Ort', '', ]" />

            <x-table.body>

                @forelse ($sites as $site)

                    <x-table.datarow
                        :values="[
                            $site->name,
                            $site->street . ' ' . $site->house_number,
                            $site->zip . ' ' . $site->city,

                        ]"

                        editUrl="{{ route('site.edit', [$customer, $site]) }}"
                        can="site_update"

                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>

