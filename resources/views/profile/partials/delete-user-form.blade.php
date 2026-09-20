<section class="space-y-4">
    <header>
        <h2 class="font-mono text-[11px] uppercase tracking-[0.12em] text-cerulean-600 dark:text-cerulean-400">
            {{ __('Konto löschen') }}
        </h2>

        <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            {{ __('Nach dem Löschen des Kontos haben Sie keinen Zugriff mehr auf Ihre Daten.') }}
        </p>
    </header>

    {{-- Fehler aus einem vorangegangenen Versuch stehen hier und nicht nur im
         Dialog: Nach dem Absenden ist der Dialog wieder zu, und eine Meldung,
         die niemand sieht, ist keine. --}}
    <x-input.fehler :messages="$errors->userDeletion->get('password')" />

    {{-- Dieselbe Rueckfrage wie ueberall sonst. Hier stand als letzte Stelle
         der Anwendung noch das Modal aus dem Breeze-Bestand, dessen Karte kein
         dark:bg trug und im Dunkelmodus weiss blieb. --}}
    <x-loeschdialog :url="route('profile.destroy')"
        :frage="__('Konto wirklich löschen?')"
        :hinweis="__('Der Zugang wird endgültig entfernt. Zur Sicherheit bitte das eigene Kennwort eingeben.')"
        :bestaetigen="__('Konto löschen')">

        <x-slot:ausloeser>
            <x-input.button type="button" color="red" x-on:click="offen = true" :label="__('Konto löschen')" />
        </x-slot:ausloeser>

        <x-slot:felder>
            <x-input.feldname for="konto_loeschen_kennwort" :value="__('Kennwort')" />
            <x-input.text id="konto_loeschen_kennwort" name="password" type="password"
                autocomplete="current-password" class="mt-1.5 block w-full" />
        </x-slot:felder>
    </x-loeschdialog>
</section>
