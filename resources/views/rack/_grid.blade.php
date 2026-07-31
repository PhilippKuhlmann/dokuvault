{{--
    Frontansicht eines Racks als CSS-Grid.
    Erwartet: $rack (mit items.device geladen), $interactive (bool).
    Interaktiv (im Livewire-Editor): jede HE ist Dropzone, Einbauten sind draggable —
    den Alpine-Zustand (drag/hover/handleDrop/previewStyle) stellt der umgebende Editor bereit.
    HE zählen von unten: Zeile 1 im Grid ist die oberste Höheneinheit.
--}}
@php
    $he = $rack->height_units;
    // Model-Klasse => Palette-Schlüssel (für die Typfarbe)
    $typeKeys = collect(config('custom.rack_device_types'))->map(fn ($v) => $v[0])->flip();
    $typeLabels = collect(config('custom.rack_device_types'))->map(fn ($v) => $v[1]);

    $colors = [
        'server' => 'bg-sky-100 border-sky-300 text-sky-900 dark:bg-sky-900/40 dark:border-sky-700 dark:text-sky-100',
        'networkswitch' => 'bg-emerald-100 border-emerald-300 text-emerald-900 dark:bg-emerald-900/40 dark:border-emerald-700 dark:text-emerald-100',
        'nas' => 'bg-amber-100 border-amber-300 text-amber-900 dark:bg-amber-900/40 dark:border-amber-700 dark:text-amber-100',
        'router' => 'bg-violet-100 border-violet-300 text-violet-900 dark:bg-violet-900/40 dark:border-violet-700 dark:text-violet-100',
        'ups' => 'bg-rose-100 border-rose-300 text-rose-900 dark:bg-rose-900/40 dark:border-rose-700 dark:text-rose-100',
    ];
    $defaultDeviceColor = 'bg-cerulean-100 border-cerulean-300 text-cerulean-900 dark:bg-cerulean-900/40 dark:border-cerulean-700 dark:text-cerulean-100';
    $passiveColor = 'bg-gray-100 border-gray-300 text-gray-600 dark:bg-gray-700/60 dark:border-gray-600 dark:text-gray-300';

    $occupied = [];
    foreach ($rack->items as $item) {
        for ($u = $item->position; $u <= $item->topUnit(); $u++) {
            $occupied[$u] = true;
        }
    }

    // Zeilenhoehe einer HE. Gross genug, dass Name, Typ und die Knoepfe
    // nebeneinander lesbar bleiben und das Ziehen sicher trifft.
    $rowHeight = '2rem';
@endphp

<div class="inline-block min-w-64 w-full max-w-md rounded-lg border-2 border-gray-300 bg-gray-50 p-2 dark:border-gray-600 dark:bg-gray-900/60"
    @if ($interactive)
        {{-- Vorschau nur ausblenden, wenn der Zeiger den Schrank wirklich verlaesst --}}
        x-on:dragleave="if (! $el.contains($event.relatedTarget)) hover = null"
    @endif>
    <div class="grid gap-y-px" style="grid-template-columns: 2.5rem 1fr;">

        {{-- HE-Skala links --}}
        @for ($u = $he; $u >= 1; $u--)
            <div class="flex items-center justify-end pr-2 text-[11px] font-mono text-gray-400 dark:text-gray-500"
                style="grid-column: 1; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};">{{ $u }}</div>
        @endfor

        {{-- Freie Höheneinheiten (im Editor zugleich Dropzonen) --}}
        @for ($u = $he; $u >= 1; $u--)
            @unless ($occupied[$u] ?? false)
                <div style="grid-column: 2; grid-row: {{ $he - $u + 1 }}; min-height: {{ $rowHeight }};"
                    @if ($interactive)
                        x-on:dragover.prevent="hover = {{ $u }}"
                        x-on:drop.prevent="handleDrop({{ $u }})"
                    @endif
                    class="rounded bg-white/40 dark:bg-gray-800/40"></div>
            @endunless
        @endfor

        {{-- Einbauten --}}
        @foreach ($rack->items as $item)
            @php
                $key = $item->device_type ? ($typeKeys[$item->device_type] ?? null) : null;
                $color = $item->device_type ? ($colors[$key] ?? $defaultDeviceColor) : $passiveColor;
            @endphp
            <div style="grid-column: 2; grid-row: {{ $he - $item->topUnit() + 1 }} / span {{ $item->height_units }}; min-height: {{ $rowHeight }};"
                @if ($interactive)
                    draggable="true"
                    x-on:dragstart="drag = { kind: 'move', id: {{ $item->id }}, he: {{ $item->height_units }} }"
                    x-on:dragend="drag = null; hover = null"
                    {{-- Auch belegte Zeilen melden sich: so zeigt die Vorschau dort rot statt gar nichts --}}
                    x-on:dragover.prevent="hover = {{ $item->position }}"
                    x-on:drop.prevent="handleDrop({{ $item->position }})"
                @endif
                class="flex items-center justify-between gap-2 rounded border px-2 text-xs {{ $color }} {{ $interactive ? 'cursor-grab active:cursor-grabbing' : '' }}"
                wire:key="rack-item-{{ $item->id }}">
                <span class="truncate font-medium">{{ $item->label() }}</span>
                <span class="flex shrink-0 items-center gap-2">
                    @if ($item->device_type && isset($typeLabels[$key]))
                        <span class="hidden sm:inline text-[10px] uppercase tracking-wide opacity-70">{{ $typeLabels[$key] }}</span>
                    @endif
                    <span class="text-[10px] font-mono opacity-70">{{ $item->height_units }} HE</span>
                    @if ($interactive)
                        <button type="button" wire:click="setHeight({{ $item->id }}, {{ $item->height_units + 1 }})"
                            class="opacity-60 hover:opacity-100" title="1 HE höher">＋</button>
                        @if ($item->height_units > 1)
                            <button type="button" wire:click="setHeight({{ $item->id }}, {{ $item->height_units - 1 }})"
                                class="opacity-60 hover:opacity-100" title="1 HE niedriger">－</button>
                        @endif
                        <button type="button" wire:click="remove({{ $item->id }})"
                            wire:confirm="Einbau entfernen?"
                            class="text-red-600 hover:text-red-700 dark:text-red-400" title="Entfernen">✕</button>
                    @endif
                </span>
            </div>
        @endforeach

        @if ($interactive)
            {{-- Drop-Vorschau: liegt ueber allem, deckt genau die HE ab, die belegt wuerden.
                 pointer-events-none, damit sie das darunterliegende drop-Ereignis nicht abfaengt. --}}
            <div x-show="drag && hover !== null"
                x-bind:style="previewStyle()"
                x-bind:class="fits()
                    ? 'border-cerulean-500 bg-cerulean-400/30'
                    : 'border-red-500 bg-red-400/30'"
                class="pointer-events-none relative z-10 flex items-center justify-center rounded border-2 border-dashed"
                style="display: none;">
                <span class="rounded px-1.5 text-[11px] font-medium tracking-wide"
                    x-bind:class="fits() ? 'text-cerulean-800 dark:text-cerulean-100' : 'text-red-800 dark:text-red-100'"
                    x-text="previewLabel()"></span>
            </div>
        @endif

    </div>
</div>
