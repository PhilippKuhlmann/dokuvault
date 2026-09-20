<x-guest-layout>
    {{-- Das Erste, was ein neuer Benutzer von DokuVault sieht. --}}
    <x-anmeldeblatt :kopf="__('Einladung')">

        <form method="POST" action="{{ route('einladung.speichern') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Willkommen. Legen Sie hier Ihr Kennwort fest, dann können Sie sich anmelden.') }}
            </p>

            <x-input.fehler :messages="$errors->get('username')" art="banner" />

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
                <x-input.fehler :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="password_confirmation" :value="__('Kennwort wiederholen')" />
                <x-input.text id="password_confirmation" name="password_confirmation" type="password" required
                    autocomplete="new-password" class="mt-1.5 block w-full" />
            </div>

            <x-input.button class="mt-7" size="blatt" :label="__('Kennwort festlegen')" />
        </form>
    </x-anmeldeblatt>
</x-guest-layout>
