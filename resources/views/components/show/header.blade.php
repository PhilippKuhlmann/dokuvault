@props(['editUrl', 'can'])

{{-- Der Slot "kernwerte" nimmt die Angaben auf, die man fast immer sucht (IP,
     Einbauort, Zugang). Sie stehen damit in derselben Zeile wie der Name statt
     irgendwo in der Karte. Ohne den Slot bleibt die Kopfzeile wie vorher. --}}
<div class="flex w-full items-start justify-between gap-4 p-3">
    {{-- Name und Kernwerte umbrechen zusammen; der Bearbeiten-Knopf bleibt
         rechts oben stehen und rutscht nicht in eine zweite Zeile. --}}
    <div class="flex min-w-0 flex-wrap items-center gap-x-6 gap-y-2">
        <div class="flex gap-3 items-center text-2xl dark:text-gray-100">
            {{ $slot }}
        </div>

        @isset($kernwerte)
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1.5">
                {{ $kernwerte }}
            </div>
        @endisset
    </div>

    @can($can)
        <div class="flex shrink-0 items-center gap-3">
            <div class="flex flex-row space-x-2">
                <a href="{{ $editUrl }}" title="{{ __('Bearbeiten') }}"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-cerulean-600 shadow-sm hover:bg-cerulean-50 hover:border-cerulean-300 focus:outline-none focus:ring-2 focus:ring-cerulean-500 transition-colors dark:bg-gray-800 dark:border-gray-600 dark:text-cerulean-400 dark:hover:bg-gray-700">
                    <x-svg.edit class="h-5 w-5" />
                </a>
            </div>
        </div>
    @endcan



</div>
