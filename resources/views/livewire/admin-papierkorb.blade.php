<div class="p-3 sm:p-5 space-y-4">

    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Papierkorb') }}</div>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ $gesamt }} {{ $gesamt === 1 ? __('Eintrag') : __('Einträge') }}
            @if ($gekuerzt)
                {{-- Ohne diesen Zusatz waere die Zahl schlicht falsch. --}}
                <span class="text-amber-600 dark:text-amber-400"
                    title="{{ __('Je Art werden höchstens :anzahl Einträge geladen.', ['anzahl' => $hoechstens]) }}">{{ __('(gekürzt)') }}</span>
            @endif
        </span>
    </div>

    {{-- Filter. Die Tage sind frei eingebbar: 21, 90 und 365 decken das
         Uebliche ab, aber nicht jede Aufbewahrungsregel haelt sich daran. --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex flex-col">
                <x-input.label :value="__('Älter als (Tage)')" />
                <div class="mt-1 flex items-center gap-2">
                    <x-input.field type="number" min="0" max="3650" wire:model.live.debounce.500ms="aelterAls" class="w-24" />
                    <div class="flex gap-1">
                        @foreach ([0 => __('alle'), 21, 90, 365] as $wert)
                            <button type="button" wire:click="$set('aelterAls', {{ is_int($wert) ? $wert : 0 }})"
                                @class([
                                    'rounded-md border px-2 py-1 text-xs transition-colors',
                                    'border-cerulean-500 bg-cerulean-50 text-cerulean-800 dark:bg-cerulean-950 dark:text-cerulean-100' => $aelterAls === (is_int($wert) ? $wert : 0),
                                    'border-gray-200 text-gray-600 hover:border-cerulean-300 dark:border-gray-600 dark:text-gray-300' => $aelterAls !== (is_int($wert) ? $wert : 0),
                                ])>
                                {{ $wert }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-col">
                <x-input.label :value="__('Art')" />
                <x-input.select name="art" wire:model.live="art" class="mt-1">
                    <option value="">{{ __('Alle Arten') }}</option>
                    @foreach ($arten as $slug => $bezeichnung)
                        <option value="{{ $slug }}">{{ __($bezeichnung) }}</option>
                    @endforeach
                </x-input.select>
            </div>

            <div class="flex flex-col">
                <x-input.label :value="__('Kunde')" />
                <x-input.select name="kunde" wire:model.live="kunde" class="mt-1">
                    <option value="">{{ __('Alle Kunden') }}</option>
                    @foreach ($kunden as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-input.select>
            </div>
        </div>

        @if ($gesamt > 0)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-700">
                @unless ($loeschenGefragt)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Endgültiges Löschen lässt sich nicht rückgängig machen — auch nicht über den Papierkorb beim Kunden.') }}
                    </p>
                    <x-input.button type="button" color="red" wire:click="$set('loeschenGefragt', true)"
                        :label="__('Angezeigte endgültig löschen')" />
                @else
                    {{-- Die Rueckfrage nennt die Zahl: "Wirklich loeschen?" ohne
                         Angabe, wie viel, ist keine Grundlage fuer ein Ja. --}}
                    <div class="w-full rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                        <div class="text-sm font-medium text-red-800 dark:text-red-300">
                            {{ $gesamt }} {{ $gesamt === 1 ? __('Eintrag') : __('Einträge') }}
                            {{ __('endgültig löschen?') }}
                        </div>
                        <p class="mt-1 text-xs text-red-700/80 dark:text-red-400/80">
                            {{ __('Mitsamt gespeicherten Kennwörtern, hinterlegten Dateien und den daran hängenden IP-Adressen.') }}
                        </p>
                        <div class="mt-4 flex justify-end gap-2">
                            <x-input.button type="button" color="gray" wire:click="$set('loeschenGefragt', false)" :label="__('Abbrechen')" />
                            <x-input.button type="button" color="red" wire:click="alleLoeschen"
                                wire:loading.attr="disabled" :label="__('Endgültig löschen')" />
                        </div>
                    </div>
                @endunless
            </div>
        @endif
    </div>

    @if ($gesamt === 0)
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="text-gray-500 dark:text-gray-400">{{ __('Kein Eintrag passt zum Filter') }}</div>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            {{-- 40rem waren zu breit: Bei 839 Pixeln Fenster lag der Loeschen-Knopf
                     ausserhalb, und ausgerechnet der ist hier der Sinn der Seite.
                 Der Kundenname darf dafuer umbrechen. --}}
            <table class="w-full min-w-[30rem] text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Kunde') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Art') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Name') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Gelöscht') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($eintraege as $zeile)
                        <tr wire:key="{{ $zeile['slug'] }}-{{ $zeile['id'] }}"
                            class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2.5">{{ $kunden[$zeile['kunde']] ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ __($zeile['art']) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ $zeile['name'] }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5" title="{{ $zeile['geloescht']?->format('d.m.Y H:i') }}">
                                {{ $zeile['geloescht']?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button"
                                    wire:click="loeschen('{{ $zeile['slug'] }}', {{ $zeile['id'] }})"
                                    wire:confirm="{{ __('Diesen Eintrag endgültig löschen?') }}"
                                    title="{{ __('Endgültig löschen') }}"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-red-600 shadow-sm transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                                    <x-svg.trash class="h-5 w-5" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($seiten > 1)
            <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                <span>{{ __('Seite') }} {{ $this->getPage() }} {{ __('von') }} {{ $seiten }}</span>
                <div class="flex gap-2">
                    <x-input.button type="button" size="sm" color="gray" wire:click="previousPage"
                        :label="__('Zurück')" x-bind:disabled="{{ $this->getPage() <= 1 ? 'true' : 'false' }}" />
                    <x-input.button type="button" size="sm" color="gray" wire:click="nextPage"
                        :label="__('Weiter')" x-bind:disabled="{{ $this->getPage() >= $seiten ? 'true' : 'false' }}" />
                </div>
            </div>
        @endif
    @endif
</div>
