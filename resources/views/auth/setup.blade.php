<x-guest-layout>
    {{-- First start of a fresh installation - see App\Support\InitialSetup. --}}
    <x-anmeldeblatt :kopf="__('Ersteinrichtung')">

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Willkommen bei DokuVault. Legen Sie hier den ersten Administrator an.') }}
            </p>

            <div>
                <x-input.feldname for="setup_code" :value="__('Einrichtungscode')" />
                <x-input.text id="setup_code" feld="setup_code" name="setup_code" type="text" required autofocus
                    autocomplete="off" class="mt-1.5 block w-full font-mono" />
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    @if ($codeFromEnv)
                        {{ __('Der Wert von SETUP_TOKEN aus der .env.') }}
                    @else
                        {{ __('Steht in der Ausgabe der Installation bzw. im Container-Log. Neu erzeugen mit „php artisan setup:code“.') }}
                    @endif
                </p>
                <x-input.fehler :messages="$errors->get('setup_code')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="name" :value="__('Name')" />
                <x-input.text id="name" feld="name" name="name" type="text" required
                    :value="old('name')" class="mt-1.5 block w-full" />
                <x-input.fehler :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="username" :value="__('Benutzername')" />
                <x-input.text id="username" feld="username" name="username" type="text" required
                    autocomplete="username" :value="old('username')" class="mt-1.5 block w-full" />
                <x-input.fehler :messages="$errors->get('username')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="email" :value="__('E-Mail')" />
                <x-input.text id="email" feld="email" name="email" type="email" required
                    :value="old('email')" class="mt-1.5 block w-full" />
                <x-input.fehler :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="password" :value="__('Kennwort')" />
                <x-input.text id="password" feld="password" name="password" type="password" required
                    autocomplete="new-password" class="mt-1.5 block w-full" />
                <x-kennwortregel />
                <x-input.fehler :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-5">
                <x-input.feldname for="password_confirmation" :value="__('Kennwort wiederholen')" />
                <x-input.text id="password_confirmation" name="password_confirmation" type="password" required
                    autocomplete="new-password" class="mt-1.5 block w-full" />
            </div>

            <x-input.button class="mt-7" size="blatt" :label="__('Administrator anlegen')" />
        </form>
    </x-anmeldeblatt>
</x-guest-layout>
