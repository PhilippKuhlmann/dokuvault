<x-guest-layout>
    {{-- Die letzte Seite, die noch auf dem Laravel-Breeze-Stand stand: ein
         zweiter Satz Eingabe-Komponenten neben dem projekteigenen, und drei
         fest verdrahtete englische Saetze. Der zweite Satz ist mit dieser
         Seite entfallen. --}}
    <x-anmeldeblatt :kopf="__('Bestätigung')">

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('Geschützter Bereich. Bitte bestätigen Sie zuerst Ihr Kennwort.') }}
            </p>

            <div>
                <x-input.feldname for="password" :value="__('Kennwort')" />
                <x-input.text id="password" feld="password" name="password" type="password" required autofocus
                    autocomplete="current-password" class="mt-1.5 block w-full" />
                <x-input.fehler :messages="$errors->get('password')" class="mt-2" />
            </div>

            <x-input.button class="mt-7" size="blatt" :label="__('Bestätigen')" />
        </form>
    </x-anmeldeblatt>
</x-guest-layout>
