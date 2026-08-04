<x-app-layout :$customer>

    <form method="POST" action="/{{ $customer->slug }}/file" enctype="multipart/form-data">
        @csrf

        @can('file_create')
            <div class="flex flex-row gap-3 p-3">
                <x-input.file id="file" name="file" class="w-96" />
                <x-input.field name="name" :placeholder="__('Dateiname')" required />
                <x-input.button :label="__('Hochladen')" />
            </div>
        @endcan

    </form>



    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', '', 'Datum', '', ]" />

            <x-table.body>

                @forelse ($files as $file)

                    <x-table.datarow
                        :values="[
                            $file->name . '.' . $file->extension,
                            'download' =>  '/' . $customer->slug . '/file/' . $file->id,
                            $file->created_at->diffForHumans()
                        ]"
                        delUrl="{{ route('file.destroy', [$customer, $file]) }}"
                        can="file_update"
                        canDel="file_delete"

                    />

                @empty
    <tr><td colspan="100" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('Noch keine Einträge vorhanden.') }}</td></tr>
@endforelse

            </x-table.body>
        </x-table.main>
    </div>

    <div class="px-3 pb-3">
        {{ $files->links() }}
    </div>

</x-app-layout>
