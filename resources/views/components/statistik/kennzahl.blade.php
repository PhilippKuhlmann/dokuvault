{{-- One key figure tile of the statistics pages. --}}
@props(['titel', 'wert', 'zusatz' => null, 'warnung' => false])

<x-panel polster="eng">
    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $titel }}</div>
    <div @class(['mt-1 text-2xl font-semibold', 'text-gray-900 dark:text-gray-100' => ! $warnung, 'text-red-600 dark:text-red-400' => $warnung])>{{ $wert }}</div>
    @if ($zusatz)
        <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $zusatz }}">{{ $zusatz }}</div>
    @endif
</x-panel>
