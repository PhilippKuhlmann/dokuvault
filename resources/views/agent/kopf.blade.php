{{-- The head of the three Auto-Doku pages: title, the tabs between them,
     the https warning and the message of the last action.

     Three pages instead of one: tokens, installed agents and what the
     network scan found had grown into one page of five panels - and each is
     a different task. The tabs repeat the sidebar on purpose: on the phone
     the sidebar is a drawer, and the count of new findings belongs in view. --}}
@php
    $neu = \App\Models\NetworkScanFinding::forCustomer($customer)->where('status', 'new')->count();
    $reiter = [
        'agenten' => [__('Agenten'), route('agent.index', $customer)],
        'funde' => [__('Neu gefunden'), route('agent.findings', $customer)],
        'tokens' => [__('Token & Installation'), route('agent.tokens', $customer)],
    ];
@endphp

<x-panel>
    <div class="text-2xl font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Auto-Dokumentation') }}</div>
    <nav class="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-b border-gray-200 text-sm dark:border-gray-700" aria-label="{{ __('Auto-Dokumentation') }}">
        @foreach ($reiter as $schluessel => [$bezeichnung, $ziel])
            <a href="{{ $ziel }}" @if ($seite === $schluessel) aria-current="page" @endif
                @class([
                    '-mb-px inline-flex items-center gap-1.5 border-b-2 pb-2',
                    'border-cerulean-600 font-medium text-cerulean-700 dark:border-cerulean-400 dark:text-cerulean-400' => $seite === $schluessel,
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-cerulean-700 dark:text-gray-400' => $seite !== $schluessel,
                ])>
                {{ $bezeichnung }}
                @if ($schluessel === 'funde' && $neu > 0)
                    <span class="rounded-full bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $neu }}</span>
                @endif
            </a>
        @endforeach
    </nav>
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
