@props([
    'selector',
    'operatingSystems'
])

<div class="flex flex-col mt-2">
    <x-input.label for="operating_system_id" :value="__('Betriebsystem')" />
    <x-input.select id="operating_system_id" name="operating_system_id">
        @foreach ($operatingSystems as $os)
            <option {{ $os->id == $selector ? 'selected' : '' }} value="{{ $os->id }}">{{ $os->name }}</option>
        @endforeach
    </x-input.select>
</div>
