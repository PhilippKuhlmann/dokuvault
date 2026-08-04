<x-app-layout :$customer>

    <x-sitetopmenu can="contactperson_create" />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'Tel.', 'E-Mail', '', ]" />

            <x-table.body>

                @forelse ($contactpersons as $contactperson)

                    <x-table.datarow
                        :values="[
                            $contactperson->first_name . ' ' . $contactperson->last_name,
                            $contactperson->phone,
                            $contactperson->mail,

                        ]"

                        editUrl="{{ route('contactperson.edit', [$customer, $contactperson]) }}"
                        can="contactperson_update"

                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

    <div class="px-3 pb-3">
        {{ $contactpersons->links() }}
    </div>

</x-app-layout>

