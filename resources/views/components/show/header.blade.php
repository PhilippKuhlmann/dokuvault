@props(['editUrl', 'can'])

<div class="flex w-full items-center justify-between p-3">
    <div class="flex gap-3 text-center items-center w-full text-2xl dark:text-gray-100">
        {{ $slot }}
    </div>

    @can($can)
        <div class="flex items-center gap-3">
            <div class="flex flex-row space-x-2">
                <a href="{{ $editUrl }}" title="{{ __('Bearbeiten') }}"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-cerulean-600 shadow-sm hover:bg-cerulean-50 hover:border-cerulean-300 focus:outline-none focus:ring-2 focus:ring-cerulean-500 transition-colors dark:bg-gray-800 dark:border-gray-600 dark:text-cerulean-400 dark:hover:bg-gray-700">
                    <x-svg.edit class="h-5 w-5" />
                </a>
            </div>
        </div>
    @endcan



</div>
