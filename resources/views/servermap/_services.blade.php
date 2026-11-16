{{-- The services of a host or guest as small chips, in the colours of the
     service catalogue (Administration); unknown ones stay neutral. Smaller
     than x-servicechip - the cards of the map are narrow. --}}
@if ($services)
    @php
        $farben = \App\Models\Service::farbzuordnung();
        $beschreibungen = \App\Models\Service::beschreibungen();
    @endphp
    <div class="mt-1 flex flex-wrap gap-1">
        @foreach ($services as $dienst)
            @php
                $schluessel = mb_strtolower($dienst);
                $stil = \App\Models\Service::kachelStil($farben[$schluessel] ?? null);
            @endphp
            <span title="{{ $beschreibungen[$schluessel] ?? $dienst }}" @if ($stil) style="{{ $stil }}" @endif
                @class(['rounded px-1.5 py-0.5 text-[11px] font-medium leading-none', 'bg-sky-50 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' => ! $stil])>{{ $dienst }}</span>
        @endforeach
    </div>
@endif
