<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8 bg-gradient-to-br from-chathams-blue-50 via-cerulean-50 to-hawkes-blue-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">

        <div class="w-full sm:max-w-md">

            <!-- Logo-Badge + Titel -->
            <div class="flex flex-col items-center mb-8">
                @if (\App\Models\Setting::logoPfad('login'))
                    {{-- Eigenes Logo ohne Badge: Ein fremdes Logo bringt seine
                         eigene Form mit, in ein blaues Quadrat gesetzt saehe es
                         aus wie aufgeklebt. --}}
                    <img src="{{ route('branding.logo', 'login') }}" alt="" class="mb-4 h-16 w-auto max-w-[16rem] object-contain" />
                @else
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
                @endif
                <span class="text-3xl text-chathams-blue-800 font-CoconPro dark:text-gray-100">
                    {{ \App\Models\Setting::appName() }}
                </span>
            </div>

            <!-- Karte -->
            <div class="w-full px-6 py-8 sm:px-8 bg-white dark:bg-gray-800 shadow-xl rounded-2xl border border-gray-100 dark:border-gray-700">

                <form method="POST" action="{{ route('einladung.speichern') }}">
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">

                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Willkommen. Vergeben Sie sich ein Kennwort, dann können Sie sich anmelden.') }}
                    </p>

                    <div>
                        <x-input.error :messages="$errors->get('username')" class="mb-3 font-DINPro-bold" />
                    </div>

                    <div>
                        <x-input.label class="text-gray-900" for="username" :value="__('Benutzername')" />
                        <x-input.text id="username" name="username" type="text" required
                            :value="old('username', $username)" class="block mt-1 w-full" />
                    </div>

                    <div class="mt-4">
                        <x-input.label class="text-gray-900" for="password" :value="__('Kennwort')" />
                        <x-input.text id="password" name="password" type="password" required autofocus
                            autocomplete="new-password" class="block mt-1 w-full" />
                        <x-input.error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input.label class="text-gray-900" for="password_confirmation" :value="__('Kennwort wiederholen')" />
                        <x-input.text id="password_confirmation" name="password_confirmation" type="password" required
                            autocomplete="new-password" class="block mt-1 w-full" />
                    </div>

                    <div class="mt-6">
                        <x-input.button class="w-full justify-center" :label="__('Kennwort setzen')" />
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
