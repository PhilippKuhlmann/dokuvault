<x-guest-layout>
    {{-- Sie trug bis eben eine Kopie der alten Huelle: Logo-Badge mit fest
         verdrahteten Farben, weiche Ecken, eigener Verlauf - eine anders
         aussehende Software hinter derselben Anmeldung.

         Erreichbar ist die Seite derzeit nur ueber ihre Adresse: Auf der
         Anmeldung steht kein Link "Kennwort vergessen". --}}
    <x-anmeldeblatt :kopf="__('Kennwort vergessen')">

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Benutzernamen eintragen. An die hinterlegte Adresse geht ein Link, hinter dem sich ein neues Kennwort vergeben lässt.') }}
            </p>

            <x-input.fehler :messages="$errors->get('username')" art="banner" />

            <div>
                <x-input.feldname for="username" :value="__('Benutzername')" />
                <x-input.text id="username" feld="username" name="username" type="text" required autofocus
                    :value="old('username')" class="mt-1.5 block w-full" />
            </div>

            <x-input.button class="mt-7" size="blatt" :label="__('Link anfordern')" />
        </form>

        {{-- Der Rueckweg gehoert nicht zum Formular, also in den Fuss. --}}
        <x-slot:fuss>
            <a href="{{ route('login') }}"
                class="underline decoration-gray-300 underline-offset-2 hover:text-cerulean-600
                       dark:decoration-gray-600 dark:hover:text-cerulean-400">
                {{ __('Zurück zur Anmeldung') }}
            </a>
        </x-slot:fuss>
    </x-anmeldeblatt>
</x-guest-layout>
