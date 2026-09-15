<x-guest-layout>
    {{--
        Die Anmeldeseite liegt auf einem gezeichneten Netzplan: Core-Router,
        Switch, drei VLANs, ein Patchfeld. Das ist das, was hinter der Tuer
        dokumentiert wird - und es sagt ohne ein Wort, wofuer das Werkzeug da
        ist. Die Maske selbst bleibt ein nuechternes Blatt darauf.

        Zur Erinnerung fuer spaeter: Die Radien sind absichtlich klein.
        rounded-lg ist in diesem Projekt 3px (app.css, --radius-lg) - die Seite
        trug frueher rounded-2xl und war damit die einzige Stelle der Anwendung
        mit weichen Ecken.
    --}}
    <div class="relative grid min-h-screen items-center gap-10 overflow-hidden px-5 py-16 sm:px-10
                lg:grid-cols-[26rem_1fr] lg:px-12 xl:gap-16 xl:px-24
                bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
                dark:from-gray-900 dark:to-cerulean-950">

        {{-- Millimeterpapier, ganz zurueckgenommen --}}
        <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                    text-chathams-blue-100 dark:text-cerulean-950"></div>

        {{-- Blasse Fassung hinter der Karte. Unter lg ist sie der ganze Plan,
             ab lg nur noch ein Streifen links - damit die Karte nicht auf
             leerem Papier liegt. --}}
        @include('auth._netzplan', ['hintergrund' => true])

        <div class="relative z-10 mx-auto w-full max-w-md rounded-lg border border-chathams-blue-200 bg-white shadow-xl
                    lg:mx-0 dark:border-gray-700 dark:bg-gray-800">

            {{-- Kopfleiste wie der Schriftkopf einer Zeichnung --}}
            <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                        font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                        dark:border-gray-700 dark:text-gray-400">
                <span>{{ __('Anmeldung') }}</span>
                <span class="normal-case">v{{ $version }}</span>
            </div>

            <div class="px-6 py-8">

                {{-- Wortmarke, und zugleich die Ueberschrift der Seite. Eine
                     eigene Zeile "Anmelden" stand frueher darunter - dasselbe
                     Wort wie auf dem Knopf, drei Zentimeter darueber.

                     Ein eigenes Logo bringt seine eigene Form mit und steht
                     deshalb fuer sich, ohne Schriftzug daneben; den Namen
                     traegt dann das alt-Attribut. --}}
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

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <x-input.error :messages="$errors->get('username')" class="mb-4 font-DINPro-bold" />

                    <div>
                        <label for="username"
                            class="block font-mono text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ __('Benutzername') }}
                        </label>
                        <x-input.text id="username" feld="username" class="mt-1.5 block w-full" type="text"
                            name="username" :value="old('username')" required autofocus autocomplete="username" />
                    </div>

                    <div class="mt-5" x-data="{ show: false }">
                        <label for="password"
                            class="block font-mono text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ __('Passwort') }}
                        </label>
                        <div class="relative mt-1.5">
                            <x-input.text id="password" class="block w-full pr-10" type="password" name="password"
                                required autocomplete="current-password" x-bind:type="show ? 'text' : 'password'" />
                            <button type="button" @click="show = !show" tabindex="-1"
                                x-bind:aria-label="show ? '{{ __('Passwort verbergen') }}' : '{{ __('Passwort anzeigen') }}'"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-cerulean-600
                                       focus:outline-hidden dark:text-gray-500 dark:hover:text-gray-300">
                                <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.7" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.7" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label for="remember_me" class="mt-5 inline-flex w-fit cursor-pointer items-center">
                        <input id="remember_me" type="checkbox" name="remember"
                            class="rounded border-gray-300 text-cerulean-600 shadow-xs focus:ring-cerulean-500">
                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">{{ __('Login merken') }}</span>
                    </label>

                    <button type="submit"
                        class="mt-7 flex w-full items-center justify-center rounded-lg bg-cerulean-600 px-4 py-2.5
                               font-DINPro-bold text-white shadow-xs transition-colors duration-150 hover:bg-cerulean-700
                               focus:outline-hidden focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2
                               dark:focus:ring-offset-gray-800">
                        {{ __('Anmelden') }}
                    </button>
                </form>
            </div>

            {{-- Der Hinweis aus den Einstellungen, etwa wer bei Fragen zum Zugang
                 hilft. Escaped, kein {!! !!}: Dies ist die eine Seite, die jeder
                 erreicht - auch ohne Zugang. --}}
            @if ($hinweis = \App\Models\Setting::anmeldeHinweis())
                <p class="border-t border-chathams-blue-100 px-6 py-4 text-sm leading-relaxed text-gray-500
                          dark:border-gray-700 dark:text-gray-400">
                    {{ $hinweis }}
                </p>
            @endif
        </div>

        {{-- Zweite Rasterspalte. Erst nach der Karte, sonst faellt der Plan in
             die schmale erste Spalte. --}}
        @include('auth._netzplan')
    </div>
</x-guest-layout>
