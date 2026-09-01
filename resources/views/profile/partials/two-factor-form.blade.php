<section id="zweite-stufe">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Zweite Stufe der Anmeldung') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Zusätzlich zum Kennwort ein Einmalcode aus einer Authentifizierungs-App. Wer nur das Kennwort kennt, kommt damit nicht mehr herein.') }}
        </p>
    </header>

    <x-input-error :messages="$errors->zweiteStufe->get('demo')" class="mt-4" />

    {{-- Frisch erzeugte Wiederherstellungscodes. Sie stehen genau einmal hier
         und danach nie wieder - sie liegen verschlüsselt, und sie noch einmal
         herzuzeigen hieße, sie noch einmal preiszugeben. --}}
    @if (session('zweite-stufe-codes'))
        <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-900/20">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                {{ __('Wiederherstellungscodes – jetzt sichern') }}
            </p>
            <p class="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                {{ __('Jeder Code ersetzt einmal den Einmalcode. Ohne sie ist ein verlorenes Telefon ein verlorener Zugang. Sie werden hier zum letzten Mal angezeigt.') }}
            </p>

            <ul class="mt-3 grid gap-1 font-mono text-sm text-amber-950 dark:text-amber-100 sm:grid-cols-2">
                @foreach (session('zweite-stufe-codes') as $code)
                    <li><x-copy :value="$code" /></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($user->hatZweiteStufe())
        {{-- Eingeschaltet --}}
        <p class="mt-6 inline-flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-900/25 dark:text-green-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            {{ __('Eingeschaltet seit') }} {{ $user->two_factor_confirmed_at->translatedFormat('d.m.Y') }}
        </p>

        <div class="mt-6 grid gap-6 sm:grid-cols-2">
            <form method="post" action="{{ route('two-factor.codes') }}" class="space-y-3">
                @csrf
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Neue Wiederherstellungscodes') }}</p>
                <x-input.label for="codes_password" :value="__('Kennwort')" class="text-gray-900" />
                <x-input.text id="codes_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->zweiteStufe->get('password')" />
                <x-input.button color="gray" :label="__('Neu erzeugen')" />
            </form>

            <form method="post" action="{{ route('two-factor.destroy') }}" class="space-y-3">
                @csrf
                @method('delete')
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Zweite Stufe abschalten') }}</p>
                <x-input.label for="aus_password" :value="__('Kennwort')" class="text-gray-900" />
                <x-input.text id="aus_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->zweiteStufeAus->get('password')" />
                <x-input.button color="red" :label="__('Abschalten')" />
            </form>
        </div>

    @elseif ($zweiteStufeGeheimnis)
        {{-- Einrichtung läuft: erst wenn ein Code stimmt, wird sie scharf. --}}
        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-start">
            <div class="shrink-0 rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:ring-gray-600">
                {!! $zweiteStufeQr !!}
            </div>

            <div class="min-w-0 flex-1 space-y-4">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Den Code in der Authentifizierungs-App scannen – oder das Geheimnis von Hand eintragen:') }}
                </p>

                <p class="break-all font-mono text-sm text-gray-900 dark:text-gray-100">
                    <x-copy :value="$zweiteStufeGeheimnis" />
                </p>

                <form method="post" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                    @csrf
                    <x-input.label for="code" :value="__('Code aus der App')" class="text-gray-900" />
                    <x-input.text id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                        autofocus class="mt-1 block w-full font-mono tracking-widest" />
                    <x-input-error :messages="$errors->zweiteStufe->get('code')" />

                    <div class="flex items-center gap-3">
                        <x-input.button :label="__('Bestätigen und einschalten')" />
                    </div>
                </form>

                <form method="post" action="{{ route('two-factor.verwerfen') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700 dark:hover:text-gray-300">
                        {{ __('Abbrechen') }}
                    </button>
                </form>
            </div>
        </div>

    @else
        {{-- Aus --}}
        <form method="post" action="{{ route('two-factor.begin') }}" class="mt-6">
            @csrf
            <x-input.button :label="__('Einrichten')" />
        </form>
    @endif
</section>
