<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8 bg-gradient-to-br from-chathams-blue-50 via-cerulean-50 to-hawkes-blue-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">

        <div class="w-full sm:max-w-md">

            <!-- Logo-Badge + Titel -->
            <div class="flex flex-col items-center mb-8">
                <div class="flex items-center justify-center w-16 h-16 mb-4 rounded-2xl bg-[#4ea1ff] shadow-lg">
                    <svg viewBox="18 12 64 76" width="44" height="44" aria-hidden="true" focusable="false">
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
                </div>
                <span class="text-3xl text-chathams-blue-800 font-CoconPro dark:text-gray-100">
                    {{ config('app.name') }}
                </span>
                <span class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Bitte melde dich an') }}
                </span>
            </div>

            <!-- Karte -->
            <div class="w-full px-6 py-8 sm:px-8 bg-white dark:bg-gray-800 shadow-xl rounded-2xl border border-gray-100 dark:border-gray-700">

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div>
                        <x-input.error :messages="$errors->get('username')" class="mb-3 font-DINPro-bold" />
                    </div>

                    <!-- Benutzername -->
                    <div>
                        <x-input.label class="text-gray-900" for="username" :value="__('Benutzername')" />
                        <x-input.text id="username" class="block mt-1 w-full" type="text" name="username"
                            :value="old('username')" required autofocus autocomplete="username" />
                    </div>

                    <!-- Passwort -->
                    <div class="mt-4" x-data="{ show: false }">
                        <x-input.label class="text-gray-900" for="password" :value="__('Passwort')" />
                        <div class="relative mt-1">
                            <x-input.text id="password" class="block w-full pr-10" type="password" name="password" required
                                autocomplete="current-password" x-bind:type="show ? 'text' : 'password'" />
                            <button type="button" @click="show = !show" tabindex="-1"
                                x-bind:aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-cerulean-600 focus:outline-none dark:text-gray-500 dark:hover:text-gray-300">
                                <!-- Auge: anzeigen -->
                                <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <!-- Auge durchgestrichen: verbergen -->
                                <svg x-show="show" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Login merken -->
                    <div class="flex items-center mt-4">
                        <label for="remember_me" class="inline-flex items-center">
                            <input id="remember_me" type="checkbox"
                                class="rounded border-gray-300 text-cerulean-600 shadow-sm focus:ring-cerulean-500"
                                name="remember">
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">{{ __('Login merken') }}</span>
                        </label>
                    </div>

                    <!-- Anmelden -->
                    <div class="mt-6">
                        <button type="submit"
                            class="w-full flex justify-center items-center py-2.5 px-4 rounded-lg text-white font-DINPro-bold bg-cerulean-600 hover:bg-cerulean-700 shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            {{ __('Anmelden') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-guest-layout>
