<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-cerulean-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-cerulean-700 focus:bg-cerulean-700 active:bg-cerulean-800 focus:outline-hidden focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
