@props([
    'selector',
    'customers'
])

<div class="flex flex-col mt-2">
    <x-input.label for="customer_id" :value="__('Kunde')" />
    <x-input.select id="customer_id" name="customer_id">
        <option value="">{{ __('Kein Kunde') }}</option>
        @foreach ($customers as $customer)
            <option {{ $customer->id == $selector ? 'selected' : '' }} value="{{ $customer->id }}">{{ $customer->name }}</option>
        @endforeach
    </x-input.select>
</div>
