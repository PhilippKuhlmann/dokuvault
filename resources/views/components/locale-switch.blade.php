{{--
    Sprachauswahl für die laufende Sitzung. Die dauerhafte Wahl steht im
    Profil; das hier greift auch auf Gastseiten und bei gesperrten Zugängen.

    Als Liste und nicht als Umschalter, weil weitere Sprachen dazukommen: Ein
    Knopf, der weiterschaltet, wird ab drei Sprachen zum Ratespiel. Die Liste
    kommt aus config('custom.locales') und wächst von selbst mit.

    Die Auswahl läuft über POST, nicht über einen Link: Sie ändert etwas.
--}}
@php
    $sprachen = config('custom.locales', []);
    $aktuell = app()->getLocale();
@endphp

@if (count($sprachen) > 1)
    <div class="relative">
        <button type="button" id="locale-switch-button" data-dropdown-toggle="dropdown-locale"
            title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-gray-200 dark:focus:ring-gray-700']) }}>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" />
            </svg>
            <span class="text-xs font-semibold uppercase leading-none">{{ $aktuell }}</span>
        </button>

        <div id="dropdown-locale"
            class="z-50 hidden my-4 min-w-[10rem] text-base list-none bg-white rounded shadow dark:bg-gray-700">
            <ul class="py-1">
                @foreach ($sprachen as $code => $bezeichnung)
                    <li>
                        <form method="POST" action="{{ route('locale.update', $code) }}">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm
                                       text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600
                                       {{ $code === $aktuell ? 'font-semibold' : '' }}"
                                @if ($code === $aktuell) aria-current="true" @endif>
                                <span>{{ $bezeichnung }}</span>
                                @if ($code === $aktuell)
                                    <svg class="w-4 h-4 shrink-0 text-cerulean-500" fill="none" stroke="currentColor"
                                        stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                @else
                                    <span class="text-xs uppercase text-gray-400 dark:text-gray-400">{{ $code }}</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
