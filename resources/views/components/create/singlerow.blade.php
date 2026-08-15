@props([
    'label',
    'name',
    'default' => '',
    'type' => 'text',
])

{{-- $attributes durchreichen: In x-create.abschnitt spannt ein Feld mit
     class="sm:col-span-2" ueber beide Rasterspalten. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col mt-2']) }}>
    <x-input.label for="{{ $name }}" value="{{ $label }}" />
    <x-input.field id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="mt-1" value="{{ old($name) ?? $default }}" />
</div>

