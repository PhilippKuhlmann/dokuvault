{{-- Der Block sitzt im Bearbeiten-Modal unter den Serverfeldern. Randlos,
     weil der Rahmen des Modals das Padding schon mitbringt. --}}
<div @class(['px-5 sm:px-6' => ! $randlos])>
    <div class="border-t border-gray-100 py-5 dark:border-gray-700">
        {{-- Der Hinweis trennt diesen Block vom Formular darueber: Dort
             speichert ein Knopf am Ende, hier wirkt jede Zeile sofort. --}}
        <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Zugänge') }}</div>
            <span class="rounded bg-cerulean-50 px-2 py-0.5 text-xs text-cerulean-700 dark:bg-cerulean-950 dark:text-cerulean-300">{{ __('speichert sofort') }}</span>
        </div>

        @if ($benutzer->isNotEmpty())
            <table class="mb-4 w-full text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700">
                    <tr>
                        <th class="py-2 pr-4 text-left font-semibold">{{ __('Benutzername') }}</th>
                        <th class="py-2 pr-4 text-left font-semibold">{{ __('Passwort') }}</th>
                        <th class="py-2 pr-4 text-left font-semibold">{{ __('Notiz') }}</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($benutzer as $eintrag)
                        <tr wire:key="ftpuser-{{ $eintrag->id }}" class="border-b border-gray-50 last:border-0 dark:border-gray-700/50">
                            <td class="py-2 pr-4 break-words text-gray-900 dark:text-gray-100">{{ $eintrag->username }}</td>
                            <td class="py-2 pr-4">
                                @if ($eintrag->password)
                                    {{-- Verdeckt mit Aufdecken und Kopieren, wie
                                         ueberall sonst - ein Kennwort steht nicht
                                         offen in einer Liste. --}}
                                    <div x-data="{ zeigen: false, kopiert: false }" class="flex items-center gap-2">
                                        <span x-show="! zeigen" class="font-mono text-gray-500 dark:text-gray-400">••••••••</span>
                                        <span x-show="zeigen" x-cloak x-ref="pw"
                                            class="break-all font-mono text-gray-900 dark:text-gray-100">{{ $eintrag->password }}</span>

                                        <button type="button" x-on:click="zeigen = ! zeigen"
                                            :title="zeigen ? '{{ __('Verbergen') }}' : '{{ __('Passwort anzeigen') }}'"
                                            class="shrink-0 text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>

                                        <button type="button" title="{{ __('Passwort kopieren') }}"
                                            x-on:click="copyText(@js($eintrag->password)); kopiert = true; setTimeout(() => kopiert = false, 1500)"
                                            class="shrink-0 text-gray-400 hover:text-cerulean-600 dark:text-gray-500 dark:hover:text-gray-300">
                                            <svg x-show="! kopiert" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25" />
                                            </svg>
                                            <svg x-show="kopiert" x-cloak class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 break-words text-gray-600 dark:text-gray-300">{{ $eintrag->note ?: '—' }}</td>
                            <td class="py-2 text-right">
                                <button type="button" wire:click="entfernen({{ $eintrag->id }})"
                                    wire:confirm="{{ __('Diesen Zugang entfernen?') }}"
                                    title="{{ __('Entfernen') }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                                    <x-svg.trash class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Noch kein Zugang hinterlegt.') }}
            </p>
        @endif

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="min-w-0">
                <x-input.label for="ftp-username" :value="__('Benutzername')" />
                <x-input.field id="ftp-username" wire:model="username" class="mt-1 w-full" />
                @error('username')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="min-w-0">
                <x-input.label for="ftp-password" :value="__('Passwort')" />
                <x-input.field id="ftp-password" wire:model="password" class="mt-1 w-full" />
                @error('password')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="min-w-0">
                <x-input.label for="ftp-note" :value="__('Notiz')" />
                <x-input.field id="ftp-note" wire:model="note" class="mt-1 w-full"
                    :placeholder="__('z. B. wofür der Zugang ist')" />
            </div>

            <div class="flex items-end">
                <x-input.button type="button" size="feld" wire:click="hinzufuegen"
                    :label="__('Hinzufügen')" class="w-full" />
            </div>
        </div>
    </div>
</div>
