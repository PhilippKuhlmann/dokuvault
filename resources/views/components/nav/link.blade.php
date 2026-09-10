@props(['url', 'name', 'target' => "_self"])


<a data-tooltip-target="{{ $name }}" data-tooltip-placement="bottom" type="button" target="{{ $target }}" href="{{ $url }}"
    class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-cerulean-600 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors">
    {{ $slot }}
</a>

<div id="{{ $name }}" role="tooltip"
    class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-xs opacity-0 tooltip transition-opacity duration-300 dark:bg-gray-700">
    {{ $name }}
    <div class="tooltip-arrow" data-popper-arrow></div>
</div>
