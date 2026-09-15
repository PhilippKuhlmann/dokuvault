{{--
    Die Huelle aller Seiten, die man ohne Anmeldung sieht: Anmeldung, zweite
    Stufe, Einladung.

    Vorher trug jede dieser Seiten denselben Block noch einmal - dreissig
    Zeilen Logo-Badge, Verlauf, Kartenrahmen, kopiert. Vier Kopien bedeuteten
    vier Stellen, die auseinanderlaufen, und genau das war passiert: Die
    Anmeldung bekam ein neues Aussehen, die anderen behielten das alte.

    $kopf steht im Schriftkopf links und sagt, was das Blatt ist - "Anmeldung",
    "Zweite Stufe", "Einladung". Rechts steht die Version, wie auf einer
    Zeichnung.

    $fuss ist der abgesetzte Bereich unter dem Formular, fuer Dinge, die nicht
    zum Formular gehoeren: der Hinweis aus den Einstellungen, oder ein
    Abbrechen-Knopf mit eigenem Formular.
--}}
@props(['kopf'])

<div class="relative grid min-h-screen items-center gap-10 overflow-hidden px-5 py-16 sm:px-10
            lg:grid-cols-[26rem_1fr] lg:px-12 xl:gap-16 xl:px-24
            bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
            dark:from-gray-900 dark:to-cerulean-950">

    {{-- Millimeterpapier, ganz zurueckgenommen --}}
    <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                text-chathams-blue-100 dark:text-cerulean-950"></div>

    {{-- Blasse Fassung hinter der Karte. Unter lg ist sie der ganze Plan, ab lg
         nur noch ein Streifen links - damit die Karte nicht auf leerem Papier
         liegt. --}}
    @include('auth._netzplan', ['hintergrund' => true])

    <div class="relative z-10 mx-auto w-full max-w-md rounded-lg border border-chathams-blue-200 bg-white shadow-xl
                lg:mx-0 dark:border-gray-700 dark:bg-gray-800">

        {{-- Kopfleiste wie der Schriftkopf einer Zeichnung --}}
        <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                    font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                    dark:border-gray-700 dark:text-gray-400">
            <span>{{ $kopf }}</span>
            <span class="normal-case">v{{ $version }}</span>
        </div>

        <div class="px-6 py-8">

            {{-- Wortmarke, und zugleich die Ueberschrift der Seite.

                 Ein eigenes Logo bringt seine eigene Form mit und steht deshalb
                 fuer sich, ohne Schriftzug daneben; den Namen traegt dann das
                 alt-Attribut. --}}
            @if (\App\Models\Setting::logoPfad('login'))
                <h1 class="mb-8">
                    <img src="{{ route('branding.logo', 'login') }}" alt="{{ \App\Models\Setting::appName() }}"
                        class="h-14 w-auto max-w-[14rem] object-contain" />
                </h1>
            @else
                <h1 class="mb-8 flex items-center gap-3">
                    <svg viewBox="18 12 64 76" width="26" height="31" aria-hidden="true" focusable="false">
                        <rect x="20" y="14" width="60" height="72" rx="6" fill="#122748" />
                        <rect x="20" y="14" width="10" height="72" rx="6" fill="#1f73d6" />
                        <line x1="70" y1="30" x2="76" y2="30" stroke="#e6ebf2" stroke-width="2.5" stroke-linecap="round" />
                        <line x1="70" y1="50" x2="76" y2="50" stroke="#e6ebf2" stroke-width="2.5" stroke-linecap="round" />
                        <line x1="70" y1="70" x2="76" y2="70" stroke="#e6ebf2" stroke-width="2.5" stroke-linecap="round" />
                        <path d="M42 45 V38 a8 8 0 0 1 16 0 V45" fill="none" stroke="#e6ebf2" stroke-width="4" stroke-linecap="round" />
                        <rect x="37" y="44" width="26" height="22" rx="4" fill="#e6ebf2" />
                        <circle cx="50" cy="52" r="3" fill="#122748" />
                        <rect x="48.5" y="54" width="3" height="7" rx="1" fill="#122748" />
                    </svg>
                    <span class="font-CoconPro text-lg uppercase tracking-[0.13em] text-chathams-blue-800 sm:text-xl dark:text-gray-100">
                        {{ \App\Models\Setting::appName() }}
                    </span>
                </h1>
            @endif

            {{-- Rueckmeldung aus einem vorangegangenen Schritt: Wer ueber eine
                 Einladung oder "Kennwort vergessen" hierher kommt, stand sonst
                 vor einer leeren Maske und wusste nicht, ob sein Kennwort
                 angekommen ist. --}}
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800
                            dark:border-green-800 dark:bg-green-900/25 dark:text-green-300" role="status">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </div>

        @isset($fuss)
            <div class="border-t border-chathams-blue-100 px-6 py-4 text-sm leading-relaxed text-gray-500
                        dark:border-gray-700 dark:text-gray-400">
                {{ $fuss }}
            </div>
        @endisset
    </div>

    @include('auth._netzplan')
</div>
