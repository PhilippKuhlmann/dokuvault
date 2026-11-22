{{-- passwordMenu (resources/js/alpine/komponenten.js): the value arrives as
     an event, gets copied, the tick shows - and the menu closes itself. --}}
<div x-data="passwordMenu" x-on:password-ready="copyValue($event.detail.value)"
    x-on:keydown.escape="close()" x-on:click.outside="close()" class="relative">
    {{-- Without a password to copy the component stays an empty div: the
         head line keeps its other buttons where they are. --}}
    @if (count($links) === 1)
        <x-input.symbolknopf wire:click="copy({{ $links[0]['id'] }})"
            :titel="__('Kennwort kopieren').': '.$links[0]['label']">
            @include('livewire._copy-password-icon')
        </x-input.symbolknopf>
    @elseif (count($links) > 1)
        {{-- Several: the same framed button as its neighbours, the number in
             the corner says there is a choice behind it. --}}
        <x-input.symbolknopf x-on:click="toggle()" x-bind:aria-expanded="open ? 'true' : 'false'" aria-haspopup="menu"
            :titel="__('Kennwort kopieren').' ('.trans_choice(':count Kennwort|:count Kennwörter', count($links)).')'" class="relative">
            @include('livewire._copy-password-icon')
            <span class="absolute -right-1.5 -top-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-cerulean-600 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white dark:bg-cerulean-500 dark:ring-gray-800">{{ count($links) }}</span>
        </x-input.symbolknopf>

        <div x-show="open" x-cloak role="menu"
            x-transition:enter="ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="absolute right-0 top-full z-30 mt-2 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800">
            <div class="border-b border-gray-100 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ __('Kennwort kopieren') }}
            </div>
            <div class="p-1.5">
                @foreach ($links as $link)
                    <button type="button" role="menuitem" wire:click="copy({{ $link['id'] }})" x-on:click="choose({{ $link['id'] }})"
                        class="group/zeile flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-cerulean-50 focus:bg-cerulean-50 focus:outline-hidden dark:hover:bg-gray-700 dark:focus:bg-gray-700">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 group-hover/zeile:bg-white group-hover/zeile:text-cerulean-600 dark:bg-gray-700 dark:text-gray-400 dark:group-hover/zeile:bg-gray-600 dark:group-hover/zeile:text-cerulean-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M16.555 3.843l3.602 3.602a2.877 2.877 0 0 1 0 4.069l-2.643 2.643a2.877 2.877 0 0 1-4.069 0l-.301-.301-6.558 6.558a2 2 0 0 1-1.239.578L5.172 21H4a1 1 0 0 1-1-1v-1.172a2 2 0 0 1 .586-1.414L4 17h2v-2h2v-2l2.144-2.144-.301-.301a2.877 2.877 0 0 1 0-4.069l2.643-2.643a2.877 2.877 0 0 1 4.069 0z" />
                                <path d="M15 9h.01" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $link['label'] }}</span>
                            @if (filled($link['user']))
                                <span class="block truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $link['user'] }}</span>
                            @endif
                        </span>
                        {{-- Copy symbol on hover, the tick once copied. --}}
                        <svg x-show="! isCopied({{ $link['id'] }})" class="h-4 w-4 shrink-0 text-gray-300 opacity-0 transition-opacity group-hover/zeile:opacity-100 group-focus/zeile:opacity-100 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7 9.667A2.667 2.667 0 0 1 9.667 7h8.666A2.667 2.667 0 0 1 21 9.667v8.666A2.667 2.667 0 0 1 18.333 21H9.667A2.667 2.667 0 0 1 7 18.333z" />
                            <path d="M4.012 16.737A2 2 0 0 1 3 15V5c0-1.1.9-2 2-2h10c.75 0 1.158.385 1.5 1" />
                        </svg>
                        <span x-show="isCopied({{ $link['id'] }})" x-cloak class="shrink-0 text-xs font-medium text-green-600 dark:text-green-400">{{ __('Kopiert') }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @error('kennwort')
        <span class="absolute right-0 top-full z-20 mt-1 w-56 rounded bg-red-50 p-2 text-xs text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</span>
    @enderror
</div>
