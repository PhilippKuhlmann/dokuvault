<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                {{ __('Dienste Gesamt') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $servicesCount }}
            </div>
        </div>
    </div>

    <x-sitetopmenu />

    <div class="m-3">
        <x-table.main>
            <x-table.head :labels="['Name', 'Beschreibung', 'Darstellung', '', ]" />

            <x-table.body>

                @foreach ($services as $service)
                    <tr class="bg-white border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700/50">
                        <td class="py-2.5 px-4 text-gray-900 dark:text-gray-100">{{ $service->name }}</td>
                        <td class="py-2.5 px-4 text-gray-600 dark:text-gray-300">{{ $service->description ?: '—' }}</td>
                        <td class="py-2.5 px-4">
                            {{-- Die Farbe direkt als Kachel: So sieht man in der Liste,
                                 wie der Dienst später am Gerät aussieht. --}}
                            <x-servicechip :name="$service->name" :farbe="$service->color" />
                        </td>
                        <td class="py-2.5 px-4">
                            <div class="flex flex-row gap-2">
                                <a href="{{ route('admin.service.edit', $service) }}" title="{{ __('Bearbeiten') }}"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-cerulean-600 shadow-sm hover:bg-cerulean-50 hover:border-cerulean-300 transition-colors dark:bg-gray-800 dark:border-gray-600 dark:text-cerulean-400 dark:hover:bg-gray-700">
                                    <x-svg.edit class="h-5 w-5" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach

            </x-table.body>
        </x-table.main>

        <div class="mt-5 mb-10">
            {{ $services->links() }}
        </div>
    </div>

</x-admin-layout>
