<x-app-layout :$customer>

    <x-sitetopmenu can="licenseaccess_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'Key', 'Download', '', ]" />

            <x-table.body>

                @forelse ($customer->licenseaccesses as $licenseaccess)

                    <x-table.datarow
                        :values="[
                            $licenseaccess->name,
                            $licenseaccess->key,
                            'download' => $licenseaccess->file_path ?  route('licenseaccess.download', [$customer, $licenseaccess]) : NULL,
                        ]"

                        editUrl="{{ route('licenseaccess.edit', [$customer, $licenseaccess]) }}"
                        can="licenseaccess_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

</x-app-layout>

