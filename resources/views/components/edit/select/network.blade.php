@props([
    'selector',
    'networks'
])

<div class="flex flex-col mt-2">
    <x-input.label for="network_id" :value="__('Netzwerk')" />
    <x-input.select id="network_id" name="network_id">
        @foreach ($networks as $network)
            <option {{ $network->id == $selector ? 'selected' : '' }} value="{{ $network->id }}">{{ $network->vlanId }} - {{ $network->description }}</option>
        @endforeach
    </x-input.select>
</div>
