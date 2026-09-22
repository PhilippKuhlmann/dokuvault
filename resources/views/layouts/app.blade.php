@props([
    'title' => ''
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title : \App\Models\Setting::appName() }}</title>
    <x-favicon />

    <script nonce="{{ $cspNonce ?? '' }}">
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="antialiased font-DINPro bg-gray-100 dark:bg-gray-900"
    x-data="{
        menuEin: (() => { try { return localStorage.getItem('menu-eingeklappt') === '1'; } catch (e) { return false; } })(),
        menuUmschalten() {
            this.menuEin = ! this.menuEin;
            try { localStorage.setItem('menu-eingeklappt', this.menuEin ? '1' : '0'); } catch (e) {}
        },
    }">

    @include('layouts.navigation')
    @include('layouts.aside')

    {{-- lg:ml-64 ist der Standard (ausgeklappt) - so steht schon vor Alpine
         das Richtige da, kein Flackern. Eingeklappt überschreibt es lg:ml-0!. --}}
    <div class="mt-16 lg:ml-64" x-bind:class="{ 'lg:ml-0!': menuEin }">
        <x-demobanner />
    </div>

    <main class="lg:ml-64" x-bind:class="{ 'lg:ml-0!': menuEin }">
        {{ $slot }}
    </main>

    @include('layouts.success')
    @include('layouts.warnung')
    @include('layouts.errors')

    <x-befehlspalette :customer="$customer ?? null" />

    @livewireScriptConfig
</body>

</html>
