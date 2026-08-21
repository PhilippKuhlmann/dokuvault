<div class="p-3 sm:p-5 space-y-4">

    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Protokoll-Historie') }}</div>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ $gesamt }} {{ $gesamt === 1 ? __('Eintrag') : __('Einträge') }}
        </span>
    </div>

    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
        {{ __('Wird ein Kennwort geändert, bleibt das bisherige so lange am Gerät nachschlagbar — verschlüsselt und nur für den, der das Gerät auch sehen darf. Für den Fall, dass jemand falsch geändert hat.') }}
    </p>

    {{-- Frist und Liste auf einer Seite: Wer hier steht, sieht was aufbewahrt
         wird - und kann in derselben Ansicht entscheiden, wie lange. --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div>
                <x-input.label :value="__('Aufbewahren (Tage)')" />
                <div class="mt-1 flex items-center gap-2">
                    <x-input.field type="number" min="0" max="3650" wire:model="tage" class="w-20" />
                    <x-input.button type="button" size="feld" wire:click="fristSpeichern" :label="__('Übernehmen')" />
                </div>
                @error('tage')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('0 heißt: gar nicht aufbewahren.') }}
                </p>
            </div>

            <div>
                <x-input.label :value="__('Suche')" />
                <x-input.field wire:model.live.debounce.400ms="suche" class="mt-1 w-full"
                    placeholder="{{ __('Gerät oder Feld') }}" />
            </div>

            <div>
                <x-input.label :value="__('Kunde')" />
                <x-input.select name="kunde" wire:model.live="kunde" class="mt-1">
                    <option value="">{{ __('Alle Kunden') }}</option>
                    @foreach ($kunden as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-input.select>
            </div>
        </div>
    </div>

    @if ($eintraege->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="text-gray-500 dark:text-gray-400">
                {{ $gesamt === 0 ? __('Noch kein Kennwort geändert worden') : __('Kein Eintrag passt zur Suche') }}
            </div>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full min-w-[42rem] text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Kunde') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Objekt') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Feld') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Bisheriges Kennwort') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Geändert') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($eintraege as $eintrag)
                        <tr wire:key="verlauf-{{ $eintrag->id }}"
                            class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2.5">{{ $eintrag->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100">
                                {{ $eintrag->subject_name ?? '#'.$eintrag->subject_id }}
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ class_basename($eintrag->subject_type) }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ __($feldNamen[$eintrag->field] ?? $eintrag->field) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                {{-- Erst auf Klick und einzeln: Eine Seite, die
                                     fünfzig alte Kennwörter im Klartext ausliefert,
                                     wäre schlechter als das Protokoll, das wir
                                     gerade davon befreit haben. --}}
                                @if (array_key_exists($eintrag->id, $aufgedeckt))
                                    <div class="flex items-center gap-2">
                                        <x-password :value="$aufgedeckt[$eintrag->id]" width="w-40" />
                                        <button type="button" wire:click="verbergen({{ $eintrag->id }})"
                                            class="text-xs text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">{{ __('verbergen') }}</button>
                                    </div>
                                @else
                                    <button type="button" wire:click="aufdecken({{ $eintrag->id }})"
                                        class="text-xs text-cerulean-600 underline hover:text-cerulean-700 dark:text-cerulean-400">{{ __('anzeigen') }}</button>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5" title="{{ $eintrag->created_at->format('d.m.Y H:i') }}">
                                {{ $eintrag->created_at->diffForHumans() }}
                                @if ($eintrag->user)
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $eintrag->user->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" wire:click="loeschen({{ $eintrag->id }})"
                                    wire:confirm="{{ __('Diesen Eintrag löschen?') }}"
                                    title="{{ __('Löschen') }}"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-red-600 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                                    <x-svg.trash class="h-5 w-5" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto">
            {{ $eintraege->links() }}
        </div>
    @endif
</div>
