{{--
    "Nur lesen" next to the title of a customer's page, when the user's team
    has less than full access there (App\Support\CustomerAccess). Without
    it the missing pencil looks like a fault. Nothing for full access.
--}}
@php
    $level = \App\Support\CustomerAccess::levelFor(auth()->user(), \App\Support\CustomerAccess::currentCustomerId());
@endphp

@if ($level && $level !== \App\Support\CustomerAccess::FULL)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 align-middle font-sans text-xs font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800']) }}
        title="{{ __('Dein Team hat bei diesem Kunden nur diese Stufe.') }}">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
            <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
        </svg>
        {{ $level === \App\Support\CustomerAccess::READ ? __('Nur lesen') : __('Nur lesen, ohne Kennwörter') }}
    </span>
@endif
