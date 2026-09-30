{{-- Aufbau wie "Weitere IP-Adressen": Bestand als Tabelle, darunter die
     gestrichelte Eingabezeile. Jede Zeile speichert sofort. --}}
<div @class([
    'mx-auto max-w-3xl px-3' => ! $eingebettet,
    'px-5 sm:px-6' => $eingebettet && ! $randlos,
])>
<div @class([
    'my-3 p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-xs dark:bg-gray-800 dark:border-gray-700' => ! $eingebettet,
    'py-5' => $eingebettet,
])>
    <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Scan-Ziele') }}</div>
        <span class="rounded bg-cerulean-50 px-2 py-0.5 text-xs text-cerulean-700 dark:bg-cerulean-950 dark:text-cerulean-300">{{ __('speichert sofort') }}</span>
    </div>

    @if ($eintraege->isNotEmpty())
        <table class="w-full text-sm mb-4">
            <thead class="text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Bezeichnung') }}</th>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Ziel') }}</th>
                    <th class="py-2 pr-4 text-left font-semibold">{{ __('Zugangsdaten') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($eintraege as $eintrag)
                    <tr class="border-b border-gray-50 last:border-0 dark:border-gray-700/50" wire:key="ziel-{{ $eintrag->id }}">
                        <td class="py-2 pr-4 align-top text-gray-900 dark:text-gray-100">
                            {{ $eintrag->name ?: '—' }}
                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $eintrag->artName() }}</div>
                        </td>
                        <td class="py-2 pr-4 align-top font-mono text-xs break-all text-gray-900 dark:text-gray-100">{{ $eintrag->target }}</td>
                        <td class="py-2 pr-4 align-top text-gray-600 dark:text-gray-300">{{ $eintrag->login?->name ?: '—' }}</td>
                        <td class="py-2 text-right align-top">
                            {{-- Loesen, nicht loeschen: Das Ziel bleibt in der
                                 Liste und an anderen Scannern. --}}
                            <button type="button" wire:click="remove({{ $eintrag->id }})"
                                class="text-sm text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">{{ __('Lösen') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-sm text-gray-400 dark:text-gray-500 mb-4">{{ __('Noch keine Scan-Ziele.') }}</div>
    @endif

    @if ($darfVerknuepfen || $darfAnlegen)
        <div class="rounded-lg border border-dashed border-gray-200 p-3 dark:border-gray-600">

            {{-- Umschalter wie bei den Zugangsdaten, auch wenn es noch nichts
                 zu verknuepfen gibt - dann steht er beim Oeffnen auf "Neu". --}}
            @if ($darfVerknuepfen && $darfAnlegen)
                <div class="mb-3 inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-600" role="tablist">
                    <button type="button" wire:click="$set('neu', false)" role="tab" aria-selected="{{ $neu ? 'false' : 'true' }}"
                        @class([
                            'rounded-md px-3 py-1 text-xs transition-colors',
                            'bg-cerulean-500 text-white' => ! $neu,
                            'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700' => $neu,
                        ])>{{ __('Vorhandenes verknüpfen') }}</button>
                    <button type="button" wire:click="$set('neu', true)" role="tab" aria-selected="{{ $neu ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-3 py-1 text-xs transition-colors',
                            'bg-cerulean-500 text-white' => $neu,
                            'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700' => ! $neu,
                        ])>{{ __('Neues anlegen') }}</button>
                </div>
            @endif

            {{-- Raster statt einer Zeile: Vier Felder und der Knopf passten im
                 Modal nicht nebeneinander - das Ziel wurde zusammengedrueckt
                 und vom Auswahlfeld daneben ueberdeckt. Das Ziel ist der
                 laengste Wert und bekommt deshalb die ganze Breite. --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if (! $neu)
                    <div class="flex min-w-0 flex-col sm:col-span-2">
                        <x-input.label :value="__('Scan-Ziel')" />
                        <x-input.select name="zielId" wire:model.live="zielId" class="mt-1 w-full">
                            <option value="">{{ $vorhandene->isEmpty() ? __('— keine weiteren vorhanden —') : __('— auswählen —') }}</option>
                            @foreach ($vorhandene as $ziel)
                                <option value="{{ $ziel->id }}">{{ $ziel->name ? $ziel->name.' – ' : '' }}{{ $ziel->target }}</option>
                            @endforeach
                        </x-input.select>
                        <x-input.fehler feld="zielId" />
                    </div>
                @else
                    <div class="flex min-w-0 flex-col">
                        <x-input.label :value="__('Bezeichnung (optional)')" />
                        <x-input.text wire:model.live.debounce.400ms="name" type="text" class="mt-1 w-full" :placeholder="__('z. B. Buchhaltung')" />
                    </div>

                    <div class="flex min-w-0 flex-col">
                        <x-input.label :value="__('Art')" />
                        <x-input.select name="kind" wire:model.live="kind" class="mt-1 w-full">
                            @foreach ($arten as $schluessel => $artName)
                                <option value="{{ $schluessel }}">{{ __($artName) }}</option>
                            @endforeach
                        </x-input.select>
                    </div>

                    <div class="flex min-w-0 flex-col sm:col-span-2">
                        <x-input.label :value="__('Ziel')" />
                        <x-input.text feld="target" wire:model.live.debounce.400ms="target" type="text" class="mt-1 w-full font-mono text-sm"
                            :placeholder="match ($kind) {
                                'email' => 'scan@firma.de',
                                'ftp' => 'sftp://srv-file01/scans',
                                'cloud' => 'OneDrive: /Scans',
                                default => '\\\\srv-file01\\scans',
                            }" />
                        <x-input.fehler feld="target" />
                    </div>

                    {{-- FTP und Freigaben brauchen meist eine Anmeldung. Sie
                         steht bei den Zugangsdaten und wird hier nur verknuepft. --}}
                    @if ($logins->isNotEmpty())
                        <div class="flex min-w-0 flex-col">
                            <x-input.label :value="__('Zugangsdaten (optional)')" />
                            <x-input.select name="login_general_id" wire:model.live="login_general_id" class="mt-1 w-full">
                                <option value="">{{ __('— keine —') }}</option>
                                @foreach ($logins as $login)
                                    <option value="{{ $login->id }}">{{ $login->name }}{{ $login->username ? ' ('.$login->username.')' : '' }}</option>
                                @endforeach
                            </x-input.select>
                            <x-input.fehler feld="login_general_id" />
                        </div>
                    @endif
                @endif

                {{-- Der Knopf unten rechts, buendig mit dem letzten Feld. --}}
                <div @class([
                    'flex items-end justify-end',
                    'sm:col-span-2' => ! $neu || $logins->isEmpty(),
                ])>
                    <x-input.button type="button" size="feld" wire:click="add" :label="$neu ? __('Anlegen') : __('Verknüpfen')" />
                </div>
            </div>
        </div>
    @endif
</div>
</div>
