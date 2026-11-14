@props(['href', 'label', 'badge' => null])

{{-- Auch auf den Seiten unterhalb dieses Links markiert: Unterseiten wie
     .../checklists/settings stehen nicht selbst in der Seitenleiste. --}}
@php $active = \App\Support\NavigationState::isActive($href); @endphp

<li>
    <a href="{{ $href }}"
        @class([
            'flex items-center w-full py-2 pl-5 pr-2 -ml-px border-l-2 text-sm transition-colors',
            'border-cerulean-600 bg-cerulean-50 text-cerulean-700 font-medium dark:bg-gray-800 dark:text-cerulean-400 dark:border-cerulean-400' => $active,
            'border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-cerulean-700 dark:text-gray-300 dark:hover:bg-gray-800' => !$active,
        ])>
        <span class="flex-1">{{ $label }}</span>
        {{-- A count that wants attention, e.g. new findings of the network scan. --}}
        @if ($badge)
            <span class="ml-2 rounded-full bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $badge }}</span>
        @endif
    </a>
</li>
