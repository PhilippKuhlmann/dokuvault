{{-- Eigene Fehlerseiten statt der von Laravel.

     Die eingebaute Seite bringt ihr eigenes CSS mit und schaltet ueber
     @media (prefers-color-scheme) - also nach der Einstellung des
     Betriebssystems. Die Anwendung schaltet dagegen ueber die Klasse "dark",
     die aus localStorage kommt. Wer die Anwendung auf dunkel gestellt hat,
     das System aber auf hell, bekam deshalb eine grell weisse 404.

     Deshalb hier dasselbe Skript wie in den Layouts, aber bewusst ohne
     Navigation und ohne Livewire: Eine Fehlerseite muss auch dann noch
     stehen, wenn darunter etwas kaputt ist. --}}

@php
    // rescue(): Bei einem 500er wegen ausgefallener Datenbank wuerde die
    // Abfrage des eigenen Namens die Fehlerseite selbst mitreissen.
    $anwendung = rescue(fn () => \App\Models\Setting::appName(), config('app.name'), false);

    // Zufaellig gezogen: Beim zweiten Mal steht etwas anderes da.
    $sprueche = config('custom.fehler_sprueche')[$code] ?? [];
    $witz = $sprueche ? __($sprueche[array_rand($sprueche)]) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $code }} · {{ $anwendung }}</title>

    <script>
        // Wie in den Layouts: erst die eigene Wahl, sonst das System.
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>

    @vite(['resources/css/app.css'])
</head>

<body class="antialiased font-DINPro bg-gray-50 dark:bg-gray-900">
    <main class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <div class="font-CoconPro text-7xl text-cerulean-600 dark:text-cerulean-400">{{ $code }}</div>

        <h1 class="mt-4 text-xl font-DINPro-bold text-gray-900 dark:text-gray-100">{{ $titel }}</h1>

        <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ $text }}</p>

        {{-- Ein trockener Satz statt eines Ausrufezeichens: Wer hier landet,
             hat gerade etwas anderes vor. Ein Augenzwinkern nimmt der Sache
             die Schaerfe, ohne den Fehler kleinzureden - deshalb klein, grau
             und unter der eigentlichen Erklaerung. --}}
        @if ($witz)
            <p class="mt-6 max-w-md text-xs italic text-gray-400 dark:text-gray-500">{{ $witz }}</p>
        @endif

        {{-- Zurueck statt eines festen Ziels: Der Weg hierher ist von aussen
             nicht bekannt, und die Startseite haengt am Kunden. --}}
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ url('/') }}"
                class="inline-flex items-center rounded-lg bg-cerulean-600 px-4 py-2 text-sm font-DINPro-bold text-white shadow-sm transition-colors hover:bg-cerulean-700">
                {{ __('Zur Startseite') }}
            </a>
            <button type="button" onclick="history.back()"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                {{ __('Zurück') }}
            </button>
        </div>
    </main>
</body>

</html>
