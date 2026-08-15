{{-- Breiter als das Formular darüber (max-w-3xl): sechs Spalten passen dort
     nicht ohne Querlauf. Bleibt wie das Formular mittig - dasselbe Muster wie
     beim Rack-Editor. --}}
<div class="mx-auto max-w-5xl px-3">
<div class="my-3 p-5 sm:p-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700">

    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
        <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Ports') }}</div>
        <div class="text-sm text-gray-400 dark:text-gray-500">
            {{ __(':dokumentiert von :gesamt dokumentiert', ['dokumentiert' => $ports->filter(fn ($p) => $p->isDocumented())->count(), 'gesamt' => $panel->port_count]) }}
        </div>
    </div>

    {{-- Blende ueber dem Formular: Beim Pflegen sieht man direkt, welche Buchse
         man gerade beschriftet und wo noch Luecken sind. Sie zeigt den
         gespeicherten Stand und aktualisiert sich beim Speichern mit. --}}
    <x-patchpanel.face :panel="$panel" />

    <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">
        {{ __('Je Port die Dosennummer, den Raum und den Switch-Port eintragen. Die Portanzahl ändert man oben im Formular.') }}
    </p>

    @if ($saved)
        <div class="p-3 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-900/40 dark:text-green-400" role="status">
            {{ __('Gespeichert.') }}
        </div>
    @endif

    @error('switchId.*')
        <div class="p-3 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-900/40 dark:text-red-400" role="alert">
            {{ $message }}
        </div>
    @enderror

    {{-- overflow-x-auto: bei vier Spalten wird es auf dem Smartphone sonst zu schmal --}}
    <div class="overflow-x-auto">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="py-2 pr-2 text-left font-semibold w-10">{{ __('Port') }}</th>
                    <th class="py-2 pr-2 text-left font-semibold w-28">{{ __('Dose') }}</th>
                    <th class="py-2 pr-2 text-left font-semibold">{{ __('Raum / Bezeichnung') }}</th>
                    <th class="py-2 pr-2 text-left font-semibold">{{ __('Switch') }}</th>
                    <th class="py-2 pr-2 text-left font-semibold w-24">{{ __('Switch-Port') }}</th>
                    <th class="py-2 pr-2 text-left font-semibold">{{ __('Notiz') }}</th>
                    <th class="py-2 w-8"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ports as $port)
                    <tr wire:key="port-{{ $port->id }}" class="border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                        <td class="py-1 pr-2 font-mono text-gray-500 dark:text-gray-400">{{ $port->number }}</td>
                        <td class="py-1 pr-2">
                            <x-input.text type="text" class="w-full text-sm py-1 font-mono"
                                wire:model="outlet.{{ $port->id }}" :placeholder="__('EG 1.01')" />
                        </td>
                        <td class="py-1 pr-2">
                            <x-input.text type="text" class="w-full text-sm py-1"
                                wire:model="label.{{ $port->id }}" :placeholder="__('z. B. Besprechung')" />
                        </td>
                        <td class="py-1 pr-2">
                            <x-input.select name="switch-{{ $port->id }}" class="w-full text-sm py-1"
                                wire:model="switchId.{{ $port->id }}">
                                <option value="">—</option>
                                @foreach ($switches as $switch)
                                    <option value="{{ $switch->id }}">{{ $switch->name }}</option>
                                @endforeach
                            </x-input.select>
                        </td>
                        <td class="py-1 pr-2">
                            <x-input.text type="text" class="w-full text-sm py-1"
                                wire:model="switchPort.{{ $port->id }}" placeholder="12" />
                        </td>
                        <td class="py-1 pr-2">
                            <x-input.text type="text" class="w-full text-sm py-1"
                                wire:model="note.{{ $port->id }}" />
                        </td>
                        <td class="py-1 text-right">
                            <button type="button" wire:click="clearPort({{ $port->id }})"
                                class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                title="{{ __('Zeile leeren') }}">✕</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex justify-end mt-4">
        <x-input.button type="button" wire:click="save" :label="__('Ports speichern')"
            wire:loading.attr="disabled" />
    </div>
</div>
</div>
