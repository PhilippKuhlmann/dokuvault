<div class="flex flex-wrap mt-20 justify-center gap-5 max-w-7xl   mx-auto sm:px-6 lg:px-8">
    <div class="w-full flex text-center justify-between">

        <div class="">
            <input wire:model.live.debounce.300ms="search" type="search" name="search" placeholder="{{ __('Suche') }}"
                class="rounded-md w-64 px-4 text-sm
            bg-gray-100 text-gray-900 dark:bg-gray-200 dark:text-gray-900 dark:border-gray-500
                focus:ring-0 focus:border-cerulean-500"
                autofocus />
        </div>



    </div>

    <div class="w-full">
        <div class="overflow-x-auto relative sm:rounded-lg">
            <table class="w-full text-sm text-left text-gray-900 dark:text-gray-400">
                <thead class="text-xs text-gray-100 bg-chathams-blue-800 dark:bg-gray-100 dark:text-gray-700">
                    <tr>
                        <th scope="col" class="py-3 px-6">
                            {{ __('Kunde') }}
                        </th>
                        <th scope="col" class="py-3 px-6">
                            {{ __('Standort') }}
                        </th>
                        <th scope="col" class="py-3 px-6">
                            URL
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($utms as $utm)
                        <tr class="border-t border-hawkes-blue-200 dark:border-gray-600 bg-hawkes-blue-100 dark:bg-gray-700">
                            <th scope="row"
                                class="py-3 px-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                {{ $utm->customer?->name }}
                            </th>
                            <td class="py-3 px-4">
                                {{ $utm->site?->name }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ $utm->urlExternal }}" target="_blank" class="hover:text-blau-100">
                                    {{ $utm->urlExternal }}</a>
                            </td>

                        </tr>
                    @endforeach



                </tbody>
            </table>
        </div>






    </div>





</div>
