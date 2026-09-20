<nav class="fixed top-0 z-50 w-full bg-white dark:bg-gray-900 ">
    <div class="px-3 py-3 lg:px-5 lg:pl-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center justify-start">
                <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar"
                    aria-controls="logo-sidebar" type="button"
                    class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg lg:hidden hover:bg-gray-100 focus:outline-hidden focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
                    <span class="sr-only">{{ __('Open sidebar') }}</span>
                    <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" fill-rule="evenodd"
                            d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z">
                        </path>
                    </svg>
                </button>
                <a href="/" class="flex ml-2 md:mr-24">
                    <x-brand />
                </a>
            </div>

            @cannot('isCustomer')
                <div class="hidden md:flex gap-1 dark:text-gray-100">

                    <x-nav.link url="{{ route('customer.search') }}" :name="__('Kundensuche')"><x-svg.search class="h-6 w-6" /> </x-nav.link>
                    <x-nav.link url="{{ route('search.global') }}" :name="__('Globale Suche')"><x-svg.db class="h-6 w-6" /> </x-nav.link>
                    {{-- Nur mit dem Recht: Die Route verlangt es, der Verweis
                         fuehrte sonst auf eine 403-Seite. --}}
                    @can('remote_search')
                        <x-nav.link url="{{ route('search.remote') }}" :name="__('Rustdesk Suche')" target="_blank"><x-svg.software.rustdesk class="h-6 w-6" /> </x-nav.link>
                    @endcan

                </div>
            @endcannot

            <div class="flex items-center">
                <div class="flex items-center ml-3">
                    {{-- Beide Schalter nebeneinander; das feste w-14 fasste nur
                         einen und brach den zweiten in die naechste Zeile um. --}}
                    <div class="flex items-center gap-1 mr-3">
                        <x-locale-switch class="dark:text-gray-400 hover:bg-cerulean-500 dark:hover:bg-gray-700" />
                        <x-theme-toggle class="dark:text-gray-400 hover:bg-cerulean-500 dark:hover:bg-gray-700" />
                    </div>
                    <x-nav.benutzermenue />
                </div>
            </div>
        </div>
    </div>
</nav>
