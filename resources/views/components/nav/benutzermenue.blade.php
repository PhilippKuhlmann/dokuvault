{{--
    Das Benutzermenü oben rechts - Knopf und Klappliste in einem.

    Vorher stand derselbe Block in navigation.blade.php und
    navigation-simple.blade.php, und die beiden waren bereits auseinander:
    Die eine Fassung hatte rounded-lg, Rahmen und shadow-lg, die andere noch
    rounded und shadow-sm. Zwei Kopien, zwei Stände - deshalb eine Stelle.

    Das Zeichen war mit 40 px so hoch wie der ganze Knopf daneben, während
    dessen Symbol nur 20 px misst. Es wirkte dadurch doppelt so schwer wie
    seine Nachbarn, obwohl es dieselbe Rolle hat. Jetzt ein 32-px-Kreis mit
    einem 20-px-Zeichen darin: dieselbe Strichstärke wie Sprache und
    Erscheinungsbild, nur rund.

    Die Klappliste trägt die Flächen der übrigen Blätter - Rahmen, weiße bzw.
    gray-800-Karte, kräftiger Schatten. Vorher war sie im Dunkeln gray-700 und
    damit heller als alles, worüber sie lag.
--}}
{{-- bereich="admin" im Adminbereich: Dort führt der erste Eintrag zurück zur
     Kundenauswahl statt hinein in die Administration. Sonst ist das Menü
     dasselbe - deshalb ein Schalter und keine zweite Kopie. --}}
@props(['bereich' => 'kunde'])

@php
    $nutzer = auth()->user();
@endphp

<div class="relative">
    {{-- bottom-end: Ohne die Angabe zentriert Flowbite die Liste unter dem
         Knopf, stiess am Fensterrand an und wurde dorthin geklemmt - sie
         klebte am Rand, waehrend der Knopf 20 px davor sitzt. So endet sie
         buendig mit ihm. --}}
    <button type="button" data-dropdown-toggle="dropdown-user" data-dropdown-placement="bottom-end"
        aria-expanded="false" title="{{ $nutzer->name }}"
        class="flex h-8 w-8 items-center justify-center rounded-full bg-chathams-blue-800 text-gray-200
               transition-colors hover:bg-chathams-blue-900 focus:outline-hidden focus:ring-2
               focus:ring-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600
               dark:focus:ring-gray-600">
        <span class="sr-only">{{ __('Benutzermenü öffnen') }}</span>
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
            aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0" />
        </svg>
    </button>

    <div id="dropdown-user"
        class="z-50 hidden min-w-56 rounded-lg border border-chathams-blue-200 bg-white text-base
               shadow-xl dark:border-gray-700 dark:bg-gray-800">

        {{-- Name und Benutzername, nicht Name und E-Mail: Die Adresse ist
             optional und bei vielen Zugängen leer - dann stand dort eine leere
             Zeile. Der Benutzername ist immer da, und er ist das, womit man
             sich anmeldet. --}}
        <div class="border-b border-chathams-blue-100 px-4 py-3 dark:border-gray-700">
            <p class="truncate text-sm text-gray-900 dark:text-gray-100">{{ $nutzer->name }}</p>
            <p class="mt-0.5 truncate font-mono text-xs text-gray-400 dark:text-gray-500">{{ $nutzer->username }}</p>
        </div>

        <ul class="py-1">
            @if ($bereich === 'admin')
                {{-- Der Weg zurück. Wer beides darf, soll nicht die Adresszeile
                     bemühen müssen. --}}
                <li>
                    <x-dropdown-link :href="route('customer.search')">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z" />
                        </svg>
                        {{ __('Zur Kundenauswahl') }}
                    </x-dropdown-link>
                </li>
            @elsecan('admin_bereich')
                {{-- Der Weg in den Admin-Bereich. Ohne ihn müsste jeder, der
                     die Rechte hat, /admin von Hand tippen - der Admin kommt
                     beim Anmelden dorthin, ein Techniker mit Admin-Rechten
                     nicht. --}}
                <li>
                    <x-dropdown-link :href="route('admin.dashboard')">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ __('Administration') }}
                    </x-dropdown-link>
                </li>
            @endif

            <li>
                <x-dropdown-link :href="route('profile.edit')">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0" />
                    </svg>
                    {{ __('Profil bearbeiten') }}
                </x-dropdown-link>
            </li>
        </ul>

        {{-- Abgesetzt: Abmelden ist nicht dasselbe wie irgendwohin wechseln. --}}
        <div class="border-t border-chathams-blue-100 py-1 dark:border-gray-700">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-dropdown-link :href="route('logout')"
                    class="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/30 dark:hover:text-red-300"
                    x-data x-on:click.prevent="$el.closest('form').submit()">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    {{ __('Abmelden') }}
                </x-dropdown-link>
            </form>
        </div>
    </div>
</div>
