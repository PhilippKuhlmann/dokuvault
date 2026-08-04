{{--
    Gemeinsame Felder von Anlegen und Bearbeiten.
    Erwartet: $item (RackCatalogItem oder null beim Anlegen).
--}}
@php
    $appearances = config('custom.rack_appearances');
    $selected = old('appearance', $item->appearance ?? 'blank');
@endphp

<x-create.singlerow :label="__('Bezeichnung')" name="name" :default="$item->name ?? ''" />

<x-create.doublerow
    :label1="__('Höheneinheiten (HE)')" name1="height_units" type1="number" :default1="$item->height_units ?? 1"
    :label2="__('Reihenfolge in der Palette')" name2="sort_order" type2="number" :default2="$item->sort_order ?? 0" />

<div class="flex flex-col mt-2" x-data="{ appearance: '{{ $selected }}' }">
    <x-input.label for="appearance" :value="__('Darstellung in der Frontansicht')" />
    <x-input.select id="appearance" name="appearance" x-model="appearance">
        @foreach ($appearances as $key => $label)
            <option value="{{ $key }}" @selected($selected === $key)>{{ __($label) }}</option>
        @endforeach
    </x-input.select>

    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
        {{ __('Vorschau mit 2 HE – die Zeichnung passt sich der eingestellten Höhe an.') }}
    </p>

    {{-- Alle Darstellungen rendern, die gewählte einblenden: so ist die Wirkung
         sofort sichtbar, ohne Speichern und ohne Server-Rundreise. --}}
    <div class="mt-1 rounded-lg border-2 border-gray-400 bg-gray-200 p-2 dark:border-gray-600 dark:bg-gray-950">
        @foreach ($appearances as $key => $label)
            <div x-show="appearance === '{{ $key }}'" x-cloak
                class="h-16 text-gray-500 dark:text-gray-400">
                <x-rack.face :appearance="$key" :he="2" />
            </div>
        @endforeach
    </div>
</div>
