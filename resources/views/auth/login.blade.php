<x-guest-layout>
    {{--
        Die Huelle - Netzplan, Karte, Schriftkopf, Wortmarke - steckt in
        x-anmeldeblatt und wird von der zweiten Stufe und der Einladung
        mitbenutzt.
    --}}
    <x-anmeldeblatt :kopf="__('Anmeldung')">

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <x-input.error :messages="$errors->get('username')" class="mb-4 font-DINPro-bold" />

            <div>
                <x-input.feldname for="username" :value="__('Benutzername')" />
                <x-input.text id="username" feld="username" class="mt-1.5 block w-full" type="text"
                    name="username" :value="old('username')" required autofocus autocomplete="username" />
            </div>

            <div class="mt-5" x-data="{ show: false }">
                <x-input.feldname for="password" :value="__('Passwort')" />
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

        {{-- Der Hinweis aus den Einstellungen, etwa wer bei Fragen zum Zugang
             hilft. Escaped, kein {!! !!}: Dies ist die eine Seite, die jeder
             erreicht - auch ohne Zugang. --}}
        @if ($hinweis = \App\Models\Setting::anmeldeHinweis())
            <x-slot:fuss>{{ $hinweis }}</x-slot:fuss>
        @endif
    </x-anmeldeblatt>
</x-guest-layout>
