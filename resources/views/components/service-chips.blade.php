{{--
    The services of a device as small chips for the head line of a card, in
    the colours of the service catalogue (x-servicechip). Smaller than in the
    body: the head line also carries name, IP and location. Nothing without
    services - an empty field would leave an empty group behind.
--}}
@props(['services' => []])

@php
    $names = array_values(array_filter(array_map('trim', (array) $services), 'filled'));
    $colours = $names ? \App\Models\Service::farbzuordnung() : [];
@endphp

{{-- Given a fixed width (class), the box stays even without services, so
     the columns of a list keep their place. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1']) }}>
    @foreach ($names as $service)
        <x-servicechip :name="$service" :farbe="$colours[mb_strtolower($service)] ?? null" class="px-2! py-0.5! text-[11px]! font-semibold" />
    @endforeach
</div>
