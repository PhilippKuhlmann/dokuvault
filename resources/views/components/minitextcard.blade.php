@props(['title'])

<div class="w-full mb-5 break-inside-avoid">
    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ $title }}
    </div>
    <div class="flex flex-wrap gap-3 text-sm text-gray-900 dark:text-white">
        {{ $slot }}
    </div>

</div>
