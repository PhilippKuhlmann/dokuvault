<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('logo.svg') }}" type="image/svg+xml">

    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>

    <style>[x-cloak]{display:none!important}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @livewireScriptConfig
</head>

<body class="antialiased font-DINPro bg-gray-100 dark:bg-gray-900">

    @include('layouts.admin.navigation')
    @include('layouts.admin.aside')

    <main class="mt-16 sm:ml-64">
        {{ $slot }}
    </main>

    @include('layouts.success')
</body>

</html>
