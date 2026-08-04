{{--
    Sprachumschalter für die laufende Sitzung. Die dauerhafte Wahl steht im
    Profil; das hier greift auch auf Gastseiten und bei gesperrten Zugängen.

    Ein Knopf statt einer Liste: Zwei nebeneinanderstehende Formulare brachen
    die Kopfzeile um, und zwei unterschiedlich betonte Kürzel lasen sich wie
    Links statt wie ein Bedienelement. Der Knopf schaltet auf die nächste
    Sprache weiter – bei zweien also hin und her.
--}}
@php
    $sprachen = array_keys(config('custom.locales', []));
    $aktuell = app()->getLocale();
    $position = array_search($aktuell, $sprachen, true);
    $naechste = $sprachen[(($position === false ? -1 : $position) + 1) % max(count($sprachen), 1)] ?? null;
@endphp

@if (count($sprachen) > 1 && $naechste)
    <form method="POST" action="{{ route('locale.update', $naechste) }}" class="inline-flex">
        @csrf
        <button type="submit"
            title="{{ __('Sprache wechseln') }}: {{ config('custom.locales')[$naechste] }}"
            aria-label="{{ __('Sprache wechseln') }}: {{ config('custom.locales')[$naechste] }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-gray-200 dark:focus:ring-gray-700']) }}>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" />
            </svg>
            <span class="text-xs font-semibold uppercase leading-none">{{ $aktuell }}</span>
        </button>
    </form>
@endif
