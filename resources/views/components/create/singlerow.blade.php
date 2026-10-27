@props([
    'label',
    'name',
    'default' => '',
    'type' => 'text',
])

{{-- Pass $attributes on: in x-create.abschnitt a field with
     class="sm:col-span-2" spans both grid columns. A password is never
     filled back in from old() - it would land in the page source. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col mt-2']) }}>
    <x-input.label for="{{ $name }}" value="{{ $label }}" />
    <x-input.field id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="mt-1" value="{{ $type === 'password' ? '' : (old($name) ?? $default) }}" />
</div>

