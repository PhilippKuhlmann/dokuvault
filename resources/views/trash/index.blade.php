<x-app-layout :$customer>

    {{-- Kopfzeile wie in den uebrigen Listen, mit Anzahl statt eines nackten
         Titels. --}}
    <x-sitetopmenu :neu="false" :titel="__('Papierkorb')">
        @if ($items->isNotEmpty())
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $items->count() }} {{ $items->count() === 1 ? __('Eintrag') : __('Einträge') }}
            </span>
        @endif
    </x-sitetopmenu>

    <div class="p-3">

        @if ($gekuerzt)
            {{-- Eine stille Kuerzung liest sich wie "mehr ist nicht da". --}}
            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
                {{ __('Es werden je Art höchstens 100 Einträge gezeigt.') }}
                @foreach ($gekuerzt as $art => $anzahl)
                    <span class="font-medium">{{ __($art) }}: {{ $anzahl }}</span>@if (! $loop->last), @endif
                @endforeach
            </div>
        @endif

        @if ($items->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.2v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                <div class="mt-3 text-gray-500 dark:text-gray-400">{{ __('Der Papierkorb ist leer') }}</div>
                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                    {{ __('Gelöschte Einträge landen hier und lassen sich zurückholen.') }}
                </p>
            </div>
        @else
            {{-- overflow-x-auto statt -hidden: Am Handy passt die Zeile nicht in
                 375 Pixel, und ohne das Scrollen war der Wiederherstellen-Knopf
                 einfach abgeschnitten. --}}
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="w-full min-w-[34rem] text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Art') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Name') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Gelöscht') }}</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr class="border-b border-gray-100 bg-white transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2.5">
                                    {{-- Die Art als Etikett: In einer gemischten Liste
                                         sucht man zuerst danach, nicht nach dem Namen. --}}
                                    <span class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ __($item['label']) }}
                                    </span>
                                </td>

                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $item['name'] }}
                                </td>

                                {{-- "vor 3 Tagen" beantwortet die Frage, die man hier
                                     stellt; der genaue Zeitpunkt steht im Hover. --}}
                                <td class="whitespace-nowrap px-4 py-2.5"
                                    title="{{ $item['deleted_at']->format('d.m.Y H:i') }}">
                                    {{ $item['deleted_at']->diffForHumans() }}
                                </td>

                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('trash.restore', [$customer, $item['type'], $item['id']]) }}">
                                        @csrf
                                        <x-input.button type="submit" size="sm" :label="__('Wiederherstellen')" />
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-app-layout>
