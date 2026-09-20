<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('Dienste Gesamt')">
            {{ $servicesCount }}
        </x-adminkachel>
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
                                <x-input.symbolknopf :titel="__('Bearbeiten')" :href="route('admin.service.edit', $service)">
                                    <x-svg.edit class="h-5 w-5" />
                                </x-input.symbolknopf>
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
