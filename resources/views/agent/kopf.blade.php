{{-- The head of the three Auto-Doku pages: title, the https warning and
     the message of the last action. Moving between the pages is the
     sidebar's job - tabs here only repeated it. --}}
@php
    $titel = [
        'agenten' => __('Agenten'),
        'funde' => __('Neu gefunden'),
        'tokens' => __('Token & Installation'),
    ][$seite];
@endphp

<x-panel>
    <div class="text-2xl font-CoconPro text-chathams-blue-800 dark:text-gray-100">
        {{ __('Auto-Doku') }} <span class="text-gray-400 dark:text-gray-500">·</span> {{ $titel }}
    </div>
</x-panel>

{{-- Agents refuse a plain-http address (except localhost): they run as
     SYSTEM/root and apply updates from it. Say so before anyone
     installs one and wonders why it will not start. --}}
@unless (str_starts_with((string) config('app.url'), 'https://') || preg_match('#^http://(localhost|127\.0\.0\.1)([:/]|$)#', (string) config('app.url')))
    <div class="p-4 rounded-xl border border-red-300 bg-red-50 text-sm text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
        {{ __('DokuVault ist unter :url ohne https erreichbar. Agenten laufen mit Administratorrechten und holen Updates von dieser Adresse – sie verweigern deshalb eine http-Adresse. Richten Sie https ein und tragen Sie die Adresse als APP_URL ein.', ['url' => config('app.url')]) }}
    </div>
@endunless

@if (session('success'))
    <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-gray-800 dark:text-green-300">{{ session('success') }}</div>
@endif
