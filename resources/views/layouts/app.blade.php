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

    <script>
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

<body class="antialiased font-DINPro bg-gray-100 dark:bg-gray-900">

    @include('layouts.navigation')
    @include('layouts.aside')

    <div class="mt-16 lg:ml-64">
        <x-demobanner />
    </div>

    <main class="lg:ml-64">
        {{ $slot }}
    </main>

    @include('layouts.success')
    @include('layouts.errors')

    @livewireScriptConfig
</body>

</html>
