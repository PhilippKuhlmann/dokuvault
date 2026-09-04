<div class="border-t border-gray-100 px-5 py-3 dark:border-gray-700">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
            {{ __('Reservierte Bereiche') }}
        </div>

        @if ($darfPflegen && ! $offen)
            <button type="button" wire:click="oeffnen"
                class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                {{ __('Bereich reservieren') }}
            </button>
        @endif
    </div>

    @if ($bereiche->isEmpty() && ! $offen)
        {{-- Nicht wortlos leer bleiben: Der Satz sagt, wofür das hier gut ist.
             Ohne ihn ist eine leere Zeile nur eine leere Zeile. --}}
        <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
            {{ __('Noch keiner. Ein Bereich belegt nichts — er hält fest, wofür ein Stück des Netzes gedacht ist.') }}
        </p>
    @endif

    @if ($bereiche->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($bereiche as $bereich)
                <span wire:key="bereich-{{ $bereich->id }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs dark:border-amber-900 dark:bg-amber-900/20">
                    <span class="font-mono tabular-nums text-amber-800 dark:text-amber-200">{{ $bereich->from_ip }} – {{ $bereich->to_ip }}</span>
                    <span class="text-amber-900 dark:text-amber-100">{{ $bereich->label }}</span>
                    <span class="text-amber-600 dark:text-amber-400">{{ $bereich->anzahl() }}&nbsp;{{ __('Adressen') }}</span>

                    @if ($bereich->note)
                        <span class="text-amber-600 dark:text-amber-400">· {{ $bereich->note }}</span>
                    @endif

                    @if ($darfPflegen)
                        <button type="button" wire:click="loeschen({{ $bereich->id }})"
                            wire:confirm="{{ __('Diesen Bereich entfernen?') }}"
                            title="{{ __('Entfernen') }}"
                            class="text-amber-500 hover:text-amber-700 dark:hover:text-amber-300">&times;</button>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    @if ($offen)
        <div class="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <x-input.fehlerliste />

            <div class="flex flex-wrap gap-3">
                <div class="flex w-32 flex-col">
                    <x-input.label for="from_ip" :value="__('Von')" />
                    <x-input.text id="from_ip" feld="from_ip" wire:model.live.debounce.400ms="from_ip"
                        type="text" class="mt-1 font-mono" placeholder="10.10.250.10" />
                </div>

                <div class="flex w-32 flex-col">
                    <x-input.label for="to_ip" :value="__('Bis')" />
                    <x-input.text id="to_ip" feld="to_ip" wire:model.live.debounce.400ms="to_ip"
                        type="text" class="mt-1 font-mono" placeholder="10.10.250.20" />
                </div>

                <div class="flex flex-1 flex-col">
                    <x-input.label for="label" :value="__('Wofür')" />
                    <x-input.text id="label" feld="label" wire:model.live.debounce.400ms="label"
                        type="text" class="mt-1" :placeholder="__('z. B. Proxmox-Server')" />
                </div>
            </div>

            <div class="mt-3 flex flex-col">
                <x-input.label for="note" :value="__('Notiz')" />
                <x-input.text id="note" feld="note" wire:model.live.debounce.400ms="note"
                    type="text" class="mt-1" :placeholder="__('optional')" />
            </div>

            <x-input.fehler feld="from_ip" />
            <x-input.fehler feld="to_ip" />
            <x-input.fehler feld="label" />
            <x-input.fehler feld="note" />

            <div class="mt-3 flex justify-end gap-2">
                <button type="button" wire:click="abbrechen"
                    class="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Abbrechen') }}
                </button>
                <button type="button" wire:click="speichern"
                    class="rounded-lg bg-cerulean-600 px-3 py-1.5 text-sm text-white hover:bg-cerulean-700">
                    {{ __('Reservieren') }}
                </button>
            </div>
        </div>
    @endif
</div>
