<x-guest-layout>
    {{-- Das Erste, was ein neuer Benutzer von DokuVault sieht. --}}
    <x-anmeldeblatt :kopf="__('Einladung')">

        <form method="POST" action="{{ route('einladung.speichern') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Willkommen. Legen Sie hier Ihr Kennwort fest, dann können Sie sich anmelden.') }}
            </p>

            <x-input.error :messages="$errors->get('username')" class="mb-4 font-DINPro-bold" />

            <div>
                <x-input.feldname for="username" :value="__('Benutzername')" />
                <x-input.text id="username" feld="username" name="username" type="text" required
                    :value="old('username', $username)" class="mt-1.5 block w-full" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="password" :value="__('Kennwort')" />
                <x-input.text id="password" feld="password" name="password" type="password" required autofocus
                    autocomplete="new-password" class="mt-1.5 block w-full" />
                <x-kennwortregel />
                <x-input.error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="password_confirmation" :value="__('Kennwort wiederholen')" />
                <x-input.text id="password_confirmation" name="password_confirmation" type="password" required
                    autocomplete="new-password" class="mt-1.5 block w-full" />
            </div>

            <button type="submit"
                class="mt-7 flex w-full items-center justify-center rounded-lg bg-cerulean-600 px-4 py-2.5
                       font-DINPro-bold text-white shadow-xs transition-colors duration-150 hover:bg-cerulean-700
                       focus:outline-hidden focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2
                       dark:focus:ring-offset-gray-800">
                {{ __('Kennwort festlegen') }}
            </button>
        </form>
    </x-anmeldeblatt>
</x-guest-layout>
