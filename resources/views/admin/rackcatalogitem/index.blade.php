<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                Katalogelemente Gesamt
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $rackCatalogItemsCount }}
            </div>
        </div>
    </div>

    <x-sitetopmenu />

    <div class="m-3">
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Passive Einbauten, die im Rack-Editor jedes Kunden zur Verfügung stehen – Patchfelder,
            Blindplatten, Fachböden und Ähnliches. Beim Einbau wird die Bezeichnung in die
            Rack-Dokumentation kopiert; Änderungen hier wirken sich deshalb nur auf künftige
            Einbauten aus, nicht auf bestehende Racks.
        </p>

        <x-table.main>
            <x-table.head :labels="['Bezeichnung', 'Höheneinheiten', 'Darstellung', 'Reihenfolge', '']" />

            <x-table.body>
                @foreach ($rackCatalogItems as $rackCatalogItem)
                    <x-table.datarow
                        :values="[
                            $rackCatalogItem->name,
                            $rackCatalogItem->height_units . ' HE',
                            config('custom.rack_appearances')[$rackCatalogItem->appearance] ?? $rackCatalogItem->appearance,
                            $rackCatalogItem->sort_order,
                        ]"
                        editUrl="{{ route('admin.rackcatalogitem.edit', $rackCatalogItem) }}"
                        can="isAdmin"
                    />
                @endforeach
            </x-table.body>
        </x-table.main>

        <div class="mt-5 mb-10">
            {{ $rackCatalogItems->links() }}
        </div>
    </div>

</x-admin-layout>
