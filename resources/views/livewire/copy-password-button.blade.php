<div x-data="{ copied: false }" x-on:password-ready="$kopieren($event.detail.wert)" class="relative">
    {{-- Without a password to copy the component stays an empty div: the
         head line keeps its other buttons where they are. --}}
    @if (count($links) === 1)
        <x-input.symbolknopf wire:click="copy({{ $links[0]['id'] }})"
            :titel="__('Kennwort kopieren').': '.$links[0]['label']">
            @include('livewire._copy-password-icon')
        </x-input.symbolknopf>
    @elseif (count($links) > 1)
        {{-- Several: a native details menu - no script of its own, closes
             with a second click. --}}
        <details class="group relative">
            <summary class="list-none [&::-webkit-details-marker]:hidden" title="{{ __('Kennwort kopieren') }}">
                <span class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-chathams-blue-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-100">
                    @include('livewire._copy-password-icon')
                    <span class="sr-only">{{ __('Kennwort kopieren') }}</span>
                </span>
            </summary>
            <div class="absolute right-0 z-20 mt-1 w-60 rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                @foreach ($links as $link)
                    <button type="button" wire:click="copy({{ $link['id'] }})"
                        class="block w-full truncate rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        {{ $link['label'] }}
                    </button>
                @endforeach
            </div>
        </details>
    @endif

    @error('kennwort')
        <span class="absolute right-0 top-full z-20 mt-1 w-56 rounded bg-red-50 p-2 text-xs text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</span>
    @enderror
</div>
