{{--
    The services of a device as small chips for the head line of a card, in
    the colours of the service catalogue (x-servicechip). Smaller than in the
    body: the head line also carries name, IP and location. Nothing without
    services - an empty field would leave an empty group behind.
--}}
@props(['dienste' => []])

@php
    $gefuellt = array_values(array_filter(array_map('trim', (array) $dienste), 'filled'));
    $farben = $gefuellt ? \App\Models\Service::farbzuordnung() : [];
@endphp

{{-- Given a fixed width (class), the box stays even without services, so
     the columns of a list keep their place. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1']) }}>
    @foreach ($gefuellt as $dienst)
        <x-servicechip :name="$dienst" :farbe="$farben[mb_strtolower($dienst)] ?? null" class="px-2! py-0.5! text-[11px]! font-semibold" />
    @endforeach
</div>
