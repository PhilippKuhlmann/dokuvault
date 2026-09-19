<x-guest-layout>
    <x-anmeldeblatt :kopf="__('Zweite Stufe')">

        <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Kennwort stimmt. Jetzt noch der Einmalcode aus der Authentifizierungs-App.') }}
            </p>

            <x-input.error :messages="$errors->get('code')" class="mb-4 font-DINPro-bold" />

            <div>
                <x-input.feldname for="code" :value="__('Einmalcode')" />
                <x-input.text id="code" feld="code" name="code" type="text" inputmode="numeric"
                    autocomplete="one-time-code" autofocus required
                    class="mt-1.5 block w-full font-mono tracking-widest" />
            </div>

            <p class="mt-3 text-xs leading-relaxed text-gray-400 dark:text-gray-500">
                {{ __('Telefon nicht zur Hand? Hier geht auch einer der Wiederherstellungscodes.') }}
            </p>

            <x-input.button class="mt-7" size="blatt" :label="__('Anmelden')" />
        </form>

        {{-- Eigenes Formular, deshalb im Fuss und nicht im Formular darueber -
             verschachtelte Formulare erlaubt HTML nicht. --}}
        <x-slot:fuss>
            <form method="POST" action="{{ route('two-factor.abbrechen') }}">
                @csrf
                <button type="submit"
                    class="text-sm text-gray-500 underline decoration-gray-300 underline-offset-2
                           hover:text-cerulean-600 dark:text-gray-400 dark:decoration-gray-600
                           dark:hover:text-cerulean-400">
                    {{ __('Abbrechen') }}
                </button>
            </form>
        </x-slot:fuss>
    </x-anmeldeblatt>
</x-guest-layout>
