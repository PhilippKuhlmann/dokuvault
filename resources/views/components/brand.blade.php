@props(['suffix' => null])

{{-- Wortmarke mit Logo-Badge. Das Motiv liegt zusätzlich als public/logo.svg für das Favicon. --}}
<span class="flex items-center gap-2">
    {{-- viewBox auf die tatsächliche Zeichnung beschnitten (Original: 0 0 100 100, davon
         nur x 20-80 / y 14-86 bemalt). Sonst bleibt das Motiv im Badge winzig. --}}
    @if (\App\Models\Setting::logoPfad('header'))
        {{-- Eigenes Logo statt des eingebauten Motivs. Ohne festen Rahmen:
             Ein fremdes Logo hat sein eigenes Seitenverhaeltnis, in ein
             Quadrat gezwungen wuerde es verzerrt oder beschnitten. --}}
        <img src="{{ route('branding.logo', 'header') }}" alt="" class="h-8 w-auto max-w-[10rem] shrink-0 object-contain" />
    @else
    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#4ea1ff] shrink-0">
        <svg viewBox="18 12 64 76" width="22" height="22" aria-hidden="true" focusable="false">
            <rect x="20" y="14" width="60" height="72" rx="8" fill="#051323"/>
            <rect x="20" y="14" width="10" height="72" rx="8" fill="#0b6fce"/>
            <line x1="70" y1="30" x2="76" y2="30" stroke="#e6ebf2" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="70" y1="50" x2="76" y2="50" stroke="#e6ebf2" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="70" y1="70" x2="76" y2="70" stroke="#e6ebf2" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M42 45 V38 a8 8 0 0 1 16 0 V45" fill="none" stroke="#e6ebf2" stroke-width="4" stroke-linecap="round"/>
            <rect x="37" y="44" width="26" height="22" rx="5" fill="#e6ebf2"/>
            <circle cx="50" cy="52" r="3" fill="#051323"/>
            <rect x="48.5" y="54" width="3" height="7" rx="1" fill="#051323"/>
        </svg>
    </span>
    @endif
    <span class="self-center text-xl font-CoconPro text-chathams-blue-800 sm:text-2xl whitespace-nowrap dark:text-gray-100">
        {{ \App\Models\Setting::appName() }}{{ $suffix }}
    </span>
</span>
