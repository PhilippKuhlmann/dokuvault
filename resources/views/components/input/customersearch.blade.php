@props(['value' => '', 'autofocus' => false])

<div {{ $attributes->merge([
    'class' => "text-center"
]) }}>
    <form method="GET" action="/customer/search" class="flex">
        <input
            type="text"
            name="search"
            placeholder="{{ __('Kundensuche') }}"
            value="{{ $value }}"
            class="border-r-0 rounded-l-sm w-full px-4 text-sm bg-gray-100 text-gray-900
                    dark:bg-gray-700 dark:text-gray-100 dark:border-gray-500
                     focus:ring-0 focus:border-cerulean-600"
            @if ($autofocus)
                autofocus
            @endif
            />
        <button type="submit" class="bg-cerulean-500 px-6 rounded-r-sm text-sm text-white hover:bg-cerulean-600 focus:bg-cerulean-600">
            {{ __('Suchen') }}
        </button>
    </form>
</div>
