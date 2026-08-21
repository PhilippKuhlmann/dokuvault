<nav class="fixed top-0 z-50 w-full bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="px-3 py-3 lg:px-5 lg:pl-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center justify-start">
                <button data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar"
                    aria-controls="logo-sidebar" type="button"
                    class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
                    <span class="sr-only">{{ __('Open sidebar') }}</span>
                    <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" fill-rule="evenodd"
                            d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z">
                        </path>
                    </svg>
                </button>
                <a href="/" class="flex ml-2 md:mr-24">
                    <x-brand suffix=" · Admin" />
                </a>
            </div>

            <div class="flex items-center">
                <div class="flex items-center ml-3">
                    {{-- Beide Schalter nebeneinander; das feste w-14 fasste nur
                         einen und brach den zweiten in die naechste Zeile um. --}}
                    <div class="flex items-center gap-1 mr-3">
                        <x-locale-switch class="dark:text-gray-400 hover:bg-cerulean-500 dark:hover:bg-gray-700" />
                        <x-theme-toggle class="dark:text-gray-400 hover:bg-cerulean-500 dark:hover:bg-gray-700" />
                    </div>
                    <div>
                        <button type="button"
                            class="flex text-sm w-10 h-10 overflow-hidden relative bg-gray-800 rounded-full focus:ring-2 focus:ring-gray-300 dark:focus:ring-gray-600"
                            aria-expanded="false" data-dropdown-toggle="dropdown-user">
                            <span class="sr-only">{{ __('Open user menu') }}</span>
                            <svg class="w-full h-full text-gray-300" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.239-8 5v1h16v-1c0-2.761-3.58-5-8-5z"/></svg>
                        </button>
                    </div>
                    <div class="z-50 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-lg shadow-lg border border-gray-100 dark:bg-gray-700 dark:divide-gray-600 dark:border-gray-600"
                        id="dropdown-user">
                        <div class="px-4 py-3" role="none">
                            <p class="text-sm text-gray-900 dark:text-white" role="none">
                                {{ auth()->user()->name }}
                            </p>
                            <p class="text-sm font-light text-gray-500 truncate dark:text-gray-300" role="none">
                                {{ auth()->user()->email }}
                            </p>
                        </div>
                        <ul class="py-1" role="none">
                            {{-- Der Weg zurueck. Wer beides darf, soll nicht die
                                 Adresszeile bemuehen muessen. --}}
                            <li>
                                <x-dropdown-link :href="route('customer.search')">{{ __('Zur Kundenauswahl') }}</x-dropdown-link>
                            </li>
                            <li>
                                <x-dropdown-link :href="route('profile.edit')">{{ __('Profil bearbeiten') }}</x-dropdown-link>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf

                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault();
                                                        this.closest('form').submit();">

                                            {{ __('Abmelden') }}
                                    </x-dropdown-link>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
