{{-- Eine Zeile der Tabelle. Der Rahmen steht in der generischen Liste,
     die Spalten bleiben beim Typ. --}}
<tr class="bg-white border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700/50">
    <td class="py-2.5 px-4 break-words font-medium text-gray-900 dark:text-gray-100">{{ $eintrag->host }}</td>
    <td class="py-2.5 px-4 break-words text-gray-600 dark:text-gray-300">{{ $eintrag->description ?: '—' }}</td>

    {{-- Die Zugaenge als Namensliste: Wer den Server sucht, sucht meist einen
         bestimmten Zugang darauf. Die Kennwoerter stehen im Bearbeiten-Modal,
         nicht offen in der Liste. --}}
    <td class="py-2.5 px-4 text-gray-600 dark:text-gray-300">
        @if ($eintrag->users->isEmpty())
            <span class="text-gray-400 dark:text-gray-500">{{ __('kein Zugang') }}</span>
        @else
            <div class="flex flex-wrap gap-1">
                @foreach ($eintrag->users as $benutzer)
                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                        {{ $benutzer->username }}
                    </span>
                @endforeach
            </div>
        @endif
    </td>

    <td class="py-2.5 px-4">
        <div class="flex items-center justify-end gap-2">
            @can('ftpserver_update')
                <button type="button" wire:click="$dispatch('objekt-bearbeiten', { typ: 'ftpserver', id: {{ $eintrag->id }} })"
                    title="{{ __('Bearbeiten') }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-cerulean-600 shadow-sm transition-colors hover:border-cerulean-300 hover:bg-cerulean-50 dark:border-gray-600 dark:bg-gray-800 dark:text-cerulean-400 dark:hover:bg-gray-700">
                    <x-svg.edit class="h-5 w-5" />
                </button>
            @endcan
        </div>
    </td>
</tr>
