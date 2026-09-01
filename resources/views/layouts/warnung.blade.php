{{--
    Ein Hinweis, der nicht von selbst verschwindet.

    Die Erfolgsmeldung nebenan blendet sich nach vier Sekunden aus - richtig
    für "gespeichert", falsch für "Ihre Wiederherstellungscodes gehen zur
    Neige". Wer das übersieht, merkt es beim nächsten verlorenen Telefon.

    Der Wert liegt als put() in der Sitzung, nicht als flash(): Zwischen dem
    Auslöser und der ersten sichtbaren Seite liegt oft noch eine Weiterleitung.
    Weggeräumt wird er hier, nachdem er ausgegeben wurde - also genau einmal,
    aber sicher einmal.
--}}
@if (session('warnung'))
    @php $warnung = session('warnung'); session()->forget('warnung'); @endphp
    <div x-data="{ show: true }" x-show="show" x-cloak
        class="z-50 fixed bottom-4 right-4 flex w-full max-w-sm items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 shadow-lg dark:border-amber-700/60 dark:bg-amber-900/30 dark:text-amber-200"
        role="alert">

        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>

        <span class="grow">{{ $warnung }}</span>

        <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100" aria-label="{{ __('Schließen') }}">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
@endif
