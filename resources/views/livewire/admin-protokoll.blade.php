@use('App\Support\Zeit')
<div class="p-3 sm:p-5 space-y-4">

    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Aktivitäten') }}</div>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            @if ($gefiltert)
                {{-- Im gefilterten Fall der Dativ: "1 von 863 Eintrag" waere
                     falsch, die Zahl davor bestimmt hier nicht den Fall. --}}
                {{ $activities->total() }} {{ __('von') }} {{ $gesamt }}
                {{ $gesamt === 1 ? __('Eintrag') : __('Einträgen') }}
            @else
                {{ $gesamt }} {{ $gesamt === 1 ? __('Eintrag') : __('Einträge') }}
            @endif
        </span>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-input.label :value="__('Suche')" />
                {{-- Volltext ueber die Eigenschaften: In einem Protokoll sucht man
                     nicht nach einem Feld, sondern nach dem, woran man sich
                     erinnert - einem Namen, einer IP, einer Seriennummer. --}}
                <x-input.field wire:model.live.debounce.400ms="suche" class="mt-1 w-full"
                    placeholder="{{ __('Name, IP, Benutzer …') }}" />
            </div>

            <div>
                <x-input.label :value="__('Ereignis')" />
                <x-input.select name="ereignis" wire:model.live="ereignis" class="mt-1">
                    <option value="">{{ __('Alle Ereignisse') }}</option>
                    @foreach ($ereignisse as $schluessel => $ereignisDaten)
                        <option value="{{ $schluessel }}">{{ __($ereignisDaten[0]) }}</option>
                    @endforeach
                </x-input.select>
            </div>

            <div>
                <x-input.label :value="__('Objektart')" />
                <x-input.select name="art" wire:model.live="art" class="mt-1">
                    <option value="">{{ __('Alle Arten') }}</option>
                    @foreach ($arten as $klasse => $bezeichnung)
                        <option value="{{ $klasse }}">{{ $bezeichnung }}</option>
                    @endforeach
                </x-input.select>
            </div>

            <div>
                <x-input.label :value="__('Benutzer')" />
                <x-input.select name="benutzer" wire:model.live="benutzer" class="mt-1">
                    <option value="">{{ __('Alle Benutzer') }}</option>
                    {{-- Nach Herkunft gruppiert: Mitarbeiter oben, darunter je
                         Kunde dessen Zugaenge. Ein Kundenzugang mit
                         Schreibrecht aendert Daten wie jeder Techniker. --}}
                    @foreach ($benutzerListe as $gruppe => $namen)
                        <optgroup label="{{ $gruppe }}">
                            @foreach ($namen as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-input.select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-700">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Zeitraum') }}</span>
            <div class="flex flex-wrap gap-1">
                @foreach ([0 => __('alles'), 1 => __('heute'), 7 => __('7 Tage'), 30 => __('30 Tage'), 90 => __('90 Tage')] as $wert => $beschriftung)
                    <button type="button" wire:click="$set('tage', {{ $wert }})"
                        @class([
                            'rounded-md border px-2.5 py-1 text-xs transition-colors',
                            'border-cerulean-500 bg-cerulean-50 text-cerulean-800 dark:bg-cerulean-950 dark:text-cerulean-100' => $tage === $wert,
                            'border-gray-200 text-gray-600 hover:border-cerulean-300 dark:border-gray-600 dark:text-gray-300' => $tage !== $wert,
                        ])>
                        {{ $beschriftung }}
                    </button>
                @endforeach
            </div>

            @if ($gefiltert)
                <button type="button" wire:click="zuruecksetzen"
                    class="ml-auto text-sm text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400">
                    {{ __('Filter zurücksetzen') }}
                </button>
            @endif
        </div>
    </div>

    @if ($activities->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="text-gray-500 dark:text-gray-400">
                {{ $gesamt === 0 ? __('Noch keine Aktivitäten') : __('Kein Eintrag passt zu den Filtern') }}
            </div>
        </div>
    @else
        {{-- Scrollen statt abschneiden: Mit overflow-hidden lagen bei 839 Pixeln
             25 Pixel der Details-Spalte ausserhalb des Rahmens. --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full min-w-[38rem] text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Zeitpunkt') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Benutzer') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Ereignis') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Objekt') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $activity)
                        @php
                            [$label, $badge] = $ereignisse[$activity->event]
                                ?? [ucfirst($activity->event ?? '—'), 'text-gray-600 bg-gray-100 dark:text-gray-300 dark:bg-gray-700'];
                            $attrs = $activity->properties['attributes'] ?? [];
                            $old = $activity->properties['old'] ?? [];
                            $felder = $activity->properties['felder'] ?? [];
                            $verlaufIds = $activity->properties['verlauf_ids'] ?? [];
                            $objectName = $attrs['name'] ?? $old['name'] ?? $activity->properties['objekt'] ?? ('#' . $activity->subject_id);
                        @endphp
                        <tr wire:key="aktivitaet-{{ $activity->id }}" x-data="{ open: false }"
                            class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                            <td class="whitespace-nowrap px-4 py-2.5" title="{{ Zeit::anzeigen($activity->created_at, 'd.m.Y H:i:s') }}">
                                {{ Zeit::anzeigen($activity->created_at) }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100">
                                {{ $activity->causer?->name ?? __('System') }}
                                {{-- Der Kunde dahinter, wenn es ein Kundenzugang
                                     war: Sonst steht dort nur ein Name, und wer
                                     das war, muesste man anderswo nachschlagen. --}}
                                @if ($activity->causer?->customer)
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $activity->causer->customer->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ __($label) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100">
                                {{ class_basename($activity->subject_type) }}
                                <span class="text-gray-400">{{ is_scalar($objectName) ? $objectName : '' }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                @if (count($verlaufIds))
                                    {{-- Das bisherige Kennwort steht nicht im
                                         Protokolleintrag, sondern kommt auf Klick aus
                                         der Historie - und laeuft mit deren Frist ab. --}}
                                    <livewire:protokoll-kennwort :ids="$verlaufIds" :felder="$felder"
                                        :key="'pw-'.$activity->id" />
                                @elseif (count($felder))
                                    {{-- Kein alter Wert vorhanden - dann wenigstens
                                         sagen, welches Kennwort gemeint war. --}}
                                    <span class="text-xs text-gray-500">
                                        {{ collect($felder)->map(fn ($f) => __(config('custom.secret_field_labels')[$f] ?? $f))->join(', ') }}
                                    </span>
                                @elseif (count($attrs) || count($old))
                                    <button type="button" @click="open = !open" class="text-sm text-cerulean-600 hover:text-cerulean-700">
                                        <span x-show="!open">{{ __('anzeigen') }}</span>
                                        <span x-show="open" x-cloak>{{ __('verbergen') }}</span>
                                    </button>
                                    <div x-show="open" x-cloak class="mt-2 space-y-1">
                                        @foreach ($attrs as $field => $new)
                                            @continue($field === 'name' && $activity->event === 'updated' && ! array_key_exists('name', $old))
                                            <div class="text-xs">
                                                <span class="text-gray-500">{{ $field }}:</span>
                                                @if ($activity->event === 'updated' && array_key_exists($field, $old))
                                                    <span class="text-red-600 line-through dark:text-red-400">{{ is_scalar($old[$field]) ? $old[$field] : json_encode($old[$field]) }}</span>
                                                    →
                                                @endif
                                                <span class="text-gray-900 dark:text-gray-100">{{ is_scalar($new) ? $new : json_encode($new) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Die Seitenzahlen stehen in einer Zeile nebeneinander: Bei vielen
             Seiten sind das mehr Pixel als das Fenster breit ist. --}}
        <div class="overflow-x-auto">
            {{ $activities->links() }}
        </div>
    @endif
</div>
