{{-- Breiter als das Formular darüber (max-w-3xl): Palette, Schema und Frontansicht
     brauchen nebeneinander Platz. Bleibt wie das Formular mittig. --}}
<div class="mx-auto max-w-5xl px-3">
<div class="my-3 p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700"
    x-data="{
        drag: null,        // { kind, he, ... } - was gerade gezogen wird
        hover: null,       // unterste HE unter dem Zeiger
        rackHeight: {{ $rack->height_units }},

        span() { return this.drag?.he ?? 1 },

        /* Belegung immer frisch aus dem DOM lesen. Ein einmal in x-data
           gebackener Plan wäre nach jeder Livewire-Änderung (z. B. HE
           vergrößern) veraltet und die Vorschau würde lügen. */
        placed() {
            return [...this.$root.querySelectorAll('[data-item-id]')].map(el => ({
                id: Number(el.dataset.itemId),
                unit: Number(el.dataset.unit),
                he: Number(el.dataset.he),
            }));
        },

        /* Passt der gezogene Einbau an die Position unter dem Zeiger? */
        fits() {
            if (! this.drag || this.hover === null) return false;
            const top = this.hover + this.span() - 1;
            if (this.hover < 1 || top > this.rackHeight) return false;
            // Beim Verschieben zählen die eigenen Höheneinheiten nicht als belegt.
            const self = this.drag.kind === 'move' ? this.drag.id : null;
            return ! this.placed().some(i =>
                i.id !== self && this.hover <= i.unit + i.he - 1 && top >= i.unit
            );
        },

        /* Vorschau deckt genau die HE ab, die belegt würden - oben am Rack abgeschnitten. */
        previewStyle() {
            if (this.hover === null) return {};
            const top = Math.min(this.hover + this.span() - 1, this.rackHeight);
            const rows = Math.max(1, top - this.hover + 1);
            return { gridColumn: '2', gridRow: `${this.rackHeight - top + 1} / span ${rows}` };
        },

        previewLabel() {
            if (! this.drag || this.hover === null) return '';
            const top = this.hover + this.span() - 1;
            const range = this.span() > 1 ? `U${this.hover}–U${top}` : `U${this.hover}`;
            return this.fits() ? range : `${range} · kein Platz`;
        },

        handleDrop(position) {
            if (! this.drag) return;
            if (this.drag.kind === 'device') $wire.placeDevice(this.drag.type, this.drag.id, position);
            if (this.drag.kind === 'catalog') $wire.placeCatalog(this.drag.id, position);
            if (this.drag.kind === 'move') $wire.move(this.drag.id, position);
            this.drag = null;
            this.hover = null;
        },
    }">

    <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100 mb-1">{{ __('Bestückung') }}</div>
    <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
        {{ __('Geräte aus der Palette auf eine freie Höheneinheit ziehen – die Vorschau zeigt, welche Einheiten belegt würden. Oder per Knopf auf den untersten freien Platz einbauen. Eingebautes lässt sich ebenfalls per Ziehen verschieben.') }}
    </p>

    @error('rack')
        <div class="p-3 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-900/40 dark:text-red-400" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="flex flex-col md:flex-row gap-6">

        {{-- Palette --}}
        <div class="md:w-64 shrink-0 space-y-4">
            @forelse ($palette as $group)
                <div wire:key="palette-{{ $group['key'] }}">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ $group['label'] }}</div>
                    <ul class="space-y-1">
                        @foreach ($group['devices'] as $device)
                            <li wire:key="palette-{{ $group['key'] }}-{{ $device->id }}"
                                draggable="true"
                                x-on:dragstart="drag = { kind: 'device', type: '{{ $group['key'] }}', id: {{ $device->id }}, he: 1 }"
                                x-on:dragend="drag = null; hover = null"
                                class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm cursor-grab active:cursor-grabbing dark:border-gray-600 dark:bg-gray-700/60 dark:text-gray-200">
                                <span class="truncate">{{ $device->name }}</span>
                                <button type="button" wire:click="quickPlaceDevice('{{ $group['key'] }}', {{ $device->id }})"
                                    class="shrink-0 text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400" title="{{ __('Auf untersten freien Platz einbauen') }}">{{ __('Einbauen') }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-sm text-gray-400 dark:text-gray-500">{{ __('Alle dokumentierten Geräte sind bereits verbaut.') }}</div>
            @endforelse

            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ __('Katalog') }}</div>
                <ul class="space-y-1">
                    @forelse ($catalog as $entry)
                        <li wire:key="catalog-{{ $entry->id }}"
                            data-he="{{ $entry->height_units }}" data-catalog-id="{{ $entry->id }}"
                            draggable="true"
                            x-on:dragstart="drag = { kind: 'catalog', id: Number($el.dataset.catalogId), he: Number($el.dataset.he) }"
                            x-on:dragend="drag = null; hover = null"
                            class="flex items-center justify-between gap-2 rounded-lg border border-dashed border-gray-300 px-2 py-1.5 text-sm text-gray-500 cursor-grab active:cursor-grabbing dark:border-gray-600 dark:text-gray-400">
                            <span class="truncate">{{ $entry->name }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="text-[10px] font-mono opacity-70">{{ $entry->height_units }} HE</span>
                                <button type="button" wire:click="quickPlaceCatalog({{ $entry->id }})"
                                    class="text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400" title="{{ __('Auf untersten freien Platz einbauen') }}">{{ __('Einbauen') }}</button>
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400 dark:text-gray-500">
                            {{ __('Noch keine Katalogelemente – im Adminbereich unter „Auswahlmenüs → Rack-Katalog" anlegen.') }}
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Links das beschriftete Arbeitsschema, rechts die gezeichnete Frontansicht.
             basis-0: beide Spalten teilen den Platz gleichmaessig auf. Ohne das
             richtet sich die Breite nach dem Inhalt, und die Frontansicht wird
             breiter als das Schema. --}}
        <div class="grow basis-0 min-w-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ __('Schema') }}</div>
            @include('rack._grid', ['rack' => $rack, 'interactive' => true])
        </div>

        <div class="grow basis-0 min-w-0 hidden lg:block">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ __('Frontansicht') }}</div>
            @include('rack._rackview', ['rack' => $rack])
        </div>

    </div>
</div>
</div>
