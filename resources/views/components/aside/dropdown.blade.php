@props(['label', 'svg', 'links'])

@php
    // Gruppe automatisch geöffnet, wenn ein Link auf die aktuelle Seite zeigt -
    // oder auf eine Seite darunter, etwa .../checklists/settings unter Checklisten.
    $open = \App\Support\NavigationState::groupIsOpen((string) $links);
@endphp

<li>
    <button type="button"
        class="group flex items-center w-full p-2 rounded-lg text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-cerulean-700 dark:text-gray-200 dark:hover:bg-gray-800"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-controls="dropdown-{{ $label }}" data-collapse-toggle="dropdown-{{ $label }}">
        <x-dynamic-component :component="$svg" class="w-5 h-5 text-gray-400 transition-colors group-hover:text-cerulean-600 dark:text-gray-500" />
        <span class="flex-1 ml-3 text-left whitespace-nowrap" sidebar-toggle-item>{{ $label }}</span>
        <x-svg.arrowdown class="w-4 h-4 text-gray-400 transition-transform group-aria-expanded:rotate-180" />
    </button>
    <ul id="dropdown-{{ $label }}" @class(['mt-1 ml-4 space-y-0.5 border-l border-gray-200 dark:border-gray-700', 'hidden' => !$open])>
        {{ $links }}
    </ul>
</li>
