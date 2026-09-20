@props(['suffix' => null])

{{-- Wortmarke mit Logo. Dieselbe Zeichnung liegt als public/favicon.png
     mit weisser Kachel fuer den Browser-Reiter. --}}
<span class="flex items-center gap-2">
    @if (\App\Models\Setting::logoPfad('header'))
        {{-- Eigenes Logo statt des eingebauten Motivs. Ohne festen Rahmen:
             Ein fremdes Logo hat sein eigenes Seitenverhaeltnis, in ein
             Quadrat gezwungen wuerde es verzerrt oder beschnitten. --}}
        <img src="{{ route('branding.logo', 'header') }}" alt="" class="h-8 w-auto max-w-40 shrink-0 object-contain" />
    @else
    {{-- Zwei Fassungen derselben Zeichnung: Das Navy des Logos (#0d1e35) ist
             fast die Farbe der dunklen Kopfleiste - dort bliebe nur das Blau
             uebrig. logo-hell.png ist dieselbe Datei mit aufgehelltem Navy,
             erzeugt aus dem Original, nicht neu gezeichnet.

             Als <img> und nicht als SVG, weil das Motiv ein Bild ist; ein
             Vektor davon waere eine Nachzeichnung und nicht dieses Logo. --}}
        <img src="{{ asset('logo.png') }}" alt="" width="32" height="32"
            class="h-8 w-8 shrink-0 dark:hidden" />
        <img src="{{ asset('logo-hell.png') }}" alt="" width="32" height="32"
            class="h-8 w-8 shrink-0 hidden dark:block" />
    </span>
    @endif
    <span class="self-center text-xl font-CoconPro text-chathams-blue-800 sm:text-2xl whitespace-nowrap dark:text-gray-100">
        {{ \App\Models\Setting::appName() }}{{ $suffix }}
    </span>
</span>
