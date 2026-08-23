<div>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                {{ __('Betriebssysteme Gesamt') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $operatingSystemsCount }}
            </div>
        </div>
    </div>

    {{-- Titel ausdruecklich: aus dem Routennamen abgeleitet waere er beim
         Livewire-Rerender leer (die laufende Route heisst dann "livewire.update"). --}}
    <x-sitetopmenu :neu="false" :titel="__(config('custom.admin_list_titles')['admin.operatingsystem'])">
        {{-- Sucht waehrend des Tippens; .debounce haelt die Anfragen im Zaum. --}}
        <label class="relative">
            <span class="sr-only">{{ __('Betriebssysteme durchsuchen') }}</span>
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
            <x-input.field wire:model.live.debounce.300ms="suche" type="search"
                :placeholder="__('Suche')"
                class="w-56 pl-9 pr-3 py-2 text-sm" />
        </label>

        {{-- :neu="false" ersetzt den eingebauten Knopf: Der zeigt sonst auf
             "/livewire/update/create" statt "/admin/operatingsystem/create". --}}
        <a href="{{ route('admin.operatingsystem.create') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('Neu') }}
        </a>
    </x-sitetopmenu>

    <div class="m-3">
        @if ($operatingSystems->isNotEmpty())
            <x-table.main>
                <x-table.head :labels="['Name', 'Support-Ende', '']" />

                <x-table.body>
                    @foreach ($operatingSystems as $operatingSystem)
                        <x-table.datarow
                            :values="[
                                $operatingSystem->name,
                                'eol' => $operatingSystem,
                            ]"
                            editUrl="{{ route('admin.operatingsystem.edit', $operatingSystem) }}"
                            can="admin_operatingsystem"
                        />
                    @endforeach
                </x-table.body>
            </x-table.main>

            <div class="mt-5 mb-10">
                {{ $operatingSystems->links() }}
            </div>
        @else
            {{-- Ohne Suchbegriff ist die Liste wirklich leer, mit Begriff hat
                 nur nichts gepasst - das ist ein Unterschied. --}}
            <x-emptystate :message="$suche !== ''
                ? __('Kein Betriebssystem passt zu „:begriff“.', ['begriff' => $suche])
                : __('Noch keine Einträge vorhanden.')" />
        @endif
    </div>

</div>
