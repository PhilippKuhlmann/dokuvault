{{--
    Auswahlfeld für eine feste Liste aus der Konfiguration (Wert => Beschriftung).
    Anders als x-create.select, das Model-Sammlungen mit id/name erwartet.
--}}
@props([
    'label',
    'name',
    'options' => [],
    'default' => null,
])

<div class="flex flex-col mt-2">
    <x-input.label for="{{ $name }}" value="{{ $label }}" />
    <x-input.select id="{{ $name }}" name="{{ $name }}" class="mt-1" {{ $attributes }}>
        @foreach ($options as $wert => $beschriftung)
            <option value="{{ $wert }}" @selected((string) (old($name) ?? $default) === (string) $wert)>{{ $beschriftung }}</option>
        @endforeach
    </x-input.select>
</div>
