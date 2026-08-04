<x-app-layout :$customer>

    <x-sitetopmenu can="licensewindows_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Betriebsystem', 'Key', 'Download', '', ]" />

            <x-table.body>

                @forelse ($licensewindowsList as $licensewindows)

                    <x-table.datarow
                        :values="[
                            $licensewindows->operatingSystem?->name ?? '—',
                            $licensewindows->key,
                            'download' => $licensewindows->file_path ?  route('licensewindows.download', [$customer, $licensewindows]) : NULL,

                        ]"

                        editUrl="{{ route('licensewindows.edit', [$customer, $licensewindows]) }}"
                        can="licensewindows_update"
                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

    <div class="px-3 pb-3">
        {{ $licensewindowsList->links() }}
    </div>

</x-app-layout>

