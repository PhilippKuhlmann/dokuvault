<x-guest-layout>
    {{-- Das Gegenstueck zu "Kennwort vergessen": Hier steht man nach dem Klick
         auf den Link aus der E-Mail. --}}
    <x-anmeldeblatt :kopf="__('Neues Kennwort')">

        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Legen Sie ein neues Kennwort fest.') }}
            </p>

            <x-input.fehler :messages="$errors->get('username')" art="banner" />

            <div>
                <x-input.feldname for="username" :value="__('Benutzername')" />
                <x-input.text id="username" feld="username" name="username" type="text" required
                    :value="old('username', $request->query('username'))" class="mt-1.5 block w-full" />
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

        <x-slot:fuss>
            <a href="{{ route('login') }}"
                class="underline decoration-gray-300 underline-offset-2 hover:text-cerulean-600
                       dark:decoration-gray-600 dark:hover:text-cerulean-400">
                {{ __('Zurück zur Anmeldung') }}
            </a>
        </x-slot:fuss>
    </x-anmeldeblatt>
</x-guest-layout>
