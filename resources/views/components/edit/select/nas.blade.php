@props([
    'selector',
    'nas'
])

<div class="flex flex-col mt-2">
    <x-input.label for="nas_id" :value="__('NAS')" />
    <x-input.select id="nas_id" name="nas_id">
        @foreach ($nas as $nas)
            <option {{ $nas->id == $selector ? 'selected' : '' }} value="{{ $nas->id }}">{{ $nas->name }}</option>
        @endforeach
    </x-input.select>
</div>
