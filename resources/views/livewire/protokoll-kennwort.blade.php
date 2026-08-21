<div class="mt-1">
    @if ($offen)
        @forelse ($werte as $eintrag)
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __($eintrag['feld']) }} {{ __('vorher') }}:</span>
                <x-password :value="$eintrag['wert']" width="w-32" />
            </div>
        @empty
            {{-- Die Frist ist abgelaufen oder jemand hat den Eintrag geloescht.
                 Dass die Aenderung stattfand, bleibt trotzdem im Protokoll. --}}
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Nicht mehr aufbewahrt') }}</span>
        @endforelse

        <button type="button" wire:click="verbergen"
            class="mt-0.5 text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ __('verbergen') }}</button>
    @else
        <button type="button" wire:click="zeigen"
            class="text-xs text-cerulean-600 underline hover:text-cerulean-700 dark:text-cerulean-400">{{ __('bisheriges Kennwort anzeigen') }}</button>
    @endif
</div>
