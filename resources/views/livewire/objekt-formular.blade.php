{{-- Knopf und Modal fuer jeden Typ aus config/forms.php.

     Der Aufbau folgt dem VLAN-Modal: fester Rahmen, Felder darin, Loeschen als
     Rueckfrage im selben Fenster statt auf einer eigenen Seite. --}}
<div class="inline">
    @can($typ.'_create')
        <button type="button" wire:click="neu"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('Neu') }}
        </button>
    @endcan

    @if ($offen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="$wire.abbrechen()">

            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">

                @unless ($loeschenGefragt)
                    <div class="mb-4 text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">
                        {{ $bearbeiteId ? __($einzahl).' '.__('bearbeiten') : __('Neu').': '.__($einzahl) }}
                    </div>

                    <div class="flex flex-col gap-3">
                        @foreach ($felder as $feld)
                            <div class="flex flex-col" wire:key="feld-{{ $feld['name'] }}">
                                <x-input.label :value="__($feld['label'])" />

                                @if ($feld['type'] === 'standort')
                                    <x-input.select :name="$feld['name']" wire:model="form.{{ $feld['name'] }}" class="mt-1">
                                        <option value="">— {{ __('bitte wählen') }} —</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </x-input.select>
                                @else
                                    <x-input.field :name="$feld['name']" wire:model="form.{{ $feld['name'] }}"
                                        type="{{ $feld['type'] }}" class="mt-1" />
                                @endif

                                @error('form.'.$feld['name'])
                                    <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        {{-- Loeschen nur beim Bearbeiten und links abgesetzt: Es soll
                             nicht neben "Speichern" liegen. --}}
                        <div>
                            @if ($bearbeiteId)
                                @can($typ.'_delete')
                                    <button type="button" wire:click="$set('loeschenGefragt', true)"
                                        class="text-sm text-red-600 hover:text-red-700 dark:text-red-400">{{ __('Löschen') }}</button>
                                @endcan
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="abbrechen"
                                class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300">{{ __('Abbrechen') }}</button>
                            <x-input.button wire:click="speichern" type="button" :label="__('Speichern')" />
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                        <div class="font-DINPro-bold text-red-800 dark:text-red-300">
                            {{ __(':objekt wirklich löschen?', ['objekt' => __($einzahl)]) }}
                        </div>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                            {{ __('Der Eintrag landet im Papierkorb und lässt sich dort wiederherstellen.') }}
                        </p>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('loeschenGefragt', false)"
                            class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300">{{ __('Abbrechen') }}</button>
                        <button type="button" wire:click="loeschen"
                            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-DINPro-bold hover:bg-red-700">{{ __('Löschen') }}</button>
                    </div>
                @endunless
            </div>
        </div>
    @endif
</div>
