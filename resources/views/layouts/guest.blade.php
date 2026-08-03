<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('logo.svg') }}" type="image/svg+xml">

    <script>
        // Vor dem Rendern setzen, um FOUC zu vermeiden
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>[x-cloak]{display:none!important}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireScriptConfig
</head>

<body class="antialiased font-DINPro text-gray-900 dark:text-gray-100">

    <x-demobanner />

    {{-- Ohne Navigationsleiste gaebe es hier sonst keinen Weg ins dunkle
         Erscheinungsbild - man saehe die Anmeldeseite immer so, wie das
         Betriebssystem es vorgibt.

         Der Wrapper ist noetig, damit der Knopf unterhalb des Demo-Banners
         ansetzt: Der laeuft im normalen Fluss, ein am Seitenanfang
         ausgerichteter Knopf laege darauf. --}}
    <div class="relative">
        <div class="absolute top-3 right-3 z-20">
            <x-theme-toggle class="text-gray-500 dark:text-gray-400 hover:bg-white/70 dark:hover:bg-gray-800" />
        </div>

        {{ $slot }}
    </div>

</body>

</html>
