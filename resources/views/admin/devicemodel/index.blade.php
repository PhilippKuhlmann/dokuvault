<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                {{ __('Gerätemodelle Gesamt') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $deviceModelsCount }}
            </div>
        </div>
    </div>

    <x-sitetopmenu />

    <div class="m-3">
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Was eine „APC Smart-UPS 1500" ist – unabhängig davon, bei welchem Kunden eine steht. Ein hier hinterlegtes Bild erscheint bei jedem Gerät, dessen Hersteller und Modell übereinstimmen, auch bei anderen Kunden und auch in Racks, die längst dokumentiert sind. Verknüpft wird über die Schreibweise, nicht über eine Auswahl: Groß- und Kleinschreibung sowie zusätzliche Leerzeichen spielen dabei keine Rolle.') }}
        </p>

        <x-table.main>
            <x-table.head :labels="['Vorschau', 'Gerätetyp', 'Hersteller', 'Modell', 'Höheneinheiten', 'Darstellung', '']" />

            <x-table.body>
                @foreach ($deviceModels as $deviceModel)
                    <x-table.datarow
                        :values="[
                            'rackface' => $deviceModel,
                            __($deviceModel->typBezeichnung()),
                            $deviceModel->manufacturer,
                            $deviceModel->model ?: '—',
                            $deviceModel->height_units . ' HE',
                            $deviceModel->drawing
                                ? config('custom.rack_model_drawings')[$deviceModel->drawing] ?? $deviceModel->drawing
                                : ($deviceModel->image_path ? __('Eigenes Bild') : __('Gezeichnet')),
                        ]"
                        editUrl="{{ route('admin.devicemodel.edit', $deviceModel) }}"
                        can="admin_catalog"
                    />
                @endforeach
            </x-table.body>
        </x-table.main>

        <div class="mt-5 mb-10">
            {{ $deviceModels->links() }}
        </div>
    </div>

</x-admin-layout>
