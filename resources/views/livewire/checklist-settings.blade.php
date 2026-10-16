@php
    $kindLabels = ['app' => __('Programme und Zugänge')];
    $toggle = 'h-5 w-9 rounded-full bg-gray-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-cerulean-600 peer-checked:after:translate-x-4 peer-focus-visible:ring-2 peer-focus-visible:ring-cerulean-500 dark:bg-gray-600 dark:peer-checked:bg-cerulean-600';
    $select = 'mt-1 block w-full rounded-lg border-gray-300 shadow-xs focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100';
@endphp

<div class="space-y-5">
    <x-panel polster="keins">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-700">
            <div>
                <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Netzlaufwerke im Kundenformular') }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Die Laufwerke kommen aus der Doku. Hier wird nur eingestellt, welche der Kunde auswählen kann.') }}</div>
            </div>
            @can('networkshare_viewAny')
                <a href="{{ route('networkshare.index', $customer) }}" class="text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Netzlaufwerke pflegen') }} →</a>
            @endcan
        </div>
        <div class="px-5 py-2">
            @forelse ($shares as $share)
                <div wire:key="share-{{ $share->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-gray-100 py-2 last:border-b-0 dark:border-gray-700">
                    <div @class(['min-w-0', 'opacity-50' => ! $share->in_onboarding_form])>
                        <div class="text-gray-900 dark:text-gray-100">
                            @if ($share->drive_letter)<span class="font-mono font-semibold">{{ $share->drive_letter }}:</span>@endif
                            {{ $share->name }}
                            @if ($share->sensitive)
                                <span class="ml-1 rounded-sm bg-amber-50 px-1.5 py-px text-[11px] text-amber-700 dark:bg-amber-900/20 dark:text-yellow-400">{{ __('Freigabe GF') }}</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-x-3 text-xs text-gray-500 dark:text-gray-400">
                            <span class="break-all font-mono">{{ $share->path }}</span>
                            <span class="font-mono">{{ __('Lesen') }}: {{ $share->readGroup?->name ?? '–' }}</span>
                            <span class="font-mono">{{ __('Schreiben') }}: {{ $share->writeGroup?->name ?? '–' }}</span>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center" title="{{ __('Im Formular anbieten') }}">
                        <input type="checkbox" class="peer sr-only" @checked($share->in_onboarding_form) wire:click="toggleShare({{ $share->id }})" aria-label="{{ __('Im Formular anbieten') }}">
                        <span class="{{ $toggle }}"></span>
                    </label>
                </div>
            @empty
                <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Für diesen Kunden sind noch keine Netzlaufwerke dokumentiert.') }}</div>
            @endforelse
        </div>
    </x-panel>

    <x-panel polster="keins">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Start-Kennwort für neue Benutzer') }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Wie das AD-Skript das Kennwort eines neuen Benutzers setzt.') }}</div>
        </div>
        <form wire:submit="savePasswordRule" class="space-y-4 px-5 py-4">
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-800 dark:text-gray-200">
                <label class="flex items-center gap-2">
                    <input type="radio" value="generated" wire:model.live="passwordMode" class="h-4 w-4 border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    {{ __('Nach Regel erzeugen') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" value="fixed" wire:model.live="passwordMode" class="h-4 w-4 border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    {{ __('Festes Start-Kennwort') }}
                </label>
            </div>

            @if ($passwordMode === 'generated')
                <div class="grid grid-cols-1 gap-3 md:grid-cols-[6rem_8rem_8rem_6rem_12rem_minmax(0,1fr)]">
                    <div>
                        <x-input.label for="password-length" :value="__('Länge')" />
                        <x-input.text id="password-length" type="number" min="6" max="32" feld="passwordLength" class="mt-1 block w-full" wire:model.live.debounce.300ms="passwordLength" />
                        <x-input.fehler feld="passwordLength" />
                    </div>
                    <div>
                        <x-input.label for="password-uppercase" :value="__('Großbuchstaben')" />
                        <x-input.text id="password-uppercase" type="number" min="1" max="4" feld="passwordUppercase" class="mt-1 block w-full" wire:model.live.debounce.300ms="passwordUppercase" />
                        <x-input.fehler feld="passwordUppercase" />
                    </div>
                    <div>
                        <x-input.label for="password-special-count" :value="__('Sonderzeichen')" />
                        <x-input.text id="password-special-count" type="number" min="1" max="4" feld="passwordSpecialCount" class="mt-1 block w-full" wire:model.live.debounce.300ms="passwordSpecialCount" />
                        <x-input.fehler feld="passwordSpecialCount" />
                    </div>
                    <div>
                        <x-input.label for="password-digits" :value="__('Ziffern')" />
                        <x-input.text id="password-digits" type="number" min="0" max="8" feld="passwordDigits" class="mt-1 block w-full" wire:model.live.debounce.300ms="passwordDigits" />
                        <x-input.fehler feld="passwordDigits" />
                    </div>
                    <div>
                        <x-input.label for="password-specials" :value="__('Erlaubte Sonderzeichen')" />
                        <x-input.text id="password-specials" feld="passwordSpecials" class="mt-1 block w-full font-mono" wire:model.live.debounce.300ms="passwordSpecials" />
                        <x-input.fehler feld="passwordSpecials" />
                    </div>
                    <div>
                        <x-input.label :value="__('Beispiel')" />
                        <div class="mt-1 flex h-[42px] items-center font-mono text-lg text-gray-900 dark:text-gray-100">{{ $passwordExample ?? '–' }}</div>
                    </div>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Der Rest sind Kleinbuchstaben. Verwechselbare Zeichen (l, I, 1, O, 0) kommen nie vor. Das Kennwort entsteht erst beim Ausführen des Skripts.') }}</div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model="forceChangeDefault" class="h-4 w-4 rounded-sm border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    {{ __('Kennwort bei der ersten Anmeldung ändern lassen – Vorgabe für neue Checklisten') }}
                </label>
            @else
                <div class="max-w-sm">
                    <x-input.label for="fixed-password" :value="__('Festes Start-Kennwort')" />
                    <x-input.text id="fixed-password" type="text" autocomplete="off" feld="fixedPassword" class="mt-1 block w-full font-mono" wire:model="fixedPassword"
                        :placeholder="$hasFixedPassword ? __('gespeichert – leer lassen zum Behalten') : __('z. B. Willkommen2026!')" />
                    <x-input.fehler feld="fixedPassword" />
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Jeder neue Benutzer bekommt dieses Kennwort und muss es bei der ersten Anmeldung ändern. Es steht im heruntergeladenen Skript – die Datei nach dem Lauf löschen.') }}</div>
            @endif

            <div class="flex justify-end">
                <x-input.button :label="__('Speichern')" />
            </div>
        </form>
    </x-panel>

    <x-panel polster="keins">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Programme im Kundenformular') }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Programme und Zugänge, die der Kunde beim neuen Mitarbeiter ankreuzen kann.') }}</div>
        </div>

        <form wire:submit="saveOption" class="grid grid-cols-1 gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4 md:grid-cols-2 xl:grid-cols-4 dark:border-gray-700 dark:bg-gray-700/30">
            <div>
                <x-input.label for="name" :value="__('Name')" />
                <x-input.text id="name" feld="name" class="mt-1 block w-full" wire:model="name" :placeholder="__('Nextcloud')" />
                <x-input.fehler feld="name" />
            </div>
            <div>
                <x-input.label for="detail" :value="__('Beschreibung')" />
                <x-input.text id="detail" class="mt-1 block w-full" wire:model="detail" :placeholder="__('z. B. cloud.kunde.de')" />
            </div>
            <div>
                <x-input.label for="ad_group_id" :value="__('AD-Gruppe')" />
                <x-input.select id="ad_group_id" name="ad_group_id" wire:model="ad_group_id" class="mt-1 w-full">
                    <option value="">— {{ __('keine') }} —</option>
                    @foreach ($adGroups as $id => $groupName)
                        <option value="{{ $id }}">{{ $groupName }}</option>
                    @endforeach
                </x-input.select>
                <x-input.fehler feld="ad_group_id" />
            </div>
            <div>
                <x-input.label for="assignee" :value="__('Zuständig')" />
                <select id="assignee" wire:model="assignee" class="{{ $select }}">
                    <option value="it">{{ __('IT') }}</option>
                    <option value="customer">{{ __('Kunde') }}</option>
                </select>
            </div>
            <div class="flex items-end md:col-span-1 xl:col-span-2">
                <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model="needs_approval" class="h-4 w-4 rounded-sm border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    {{ __('braucht Freigabe der Geschäftsführung') }}
                </label>
            </div>
            <div class="flex items-end justify-end gap-2">
                @if ($editingId)
                    <x-input.button type="button" color="gray" size="feld" wire:click="cancelEdit" :label="__('Abbrechen')" />
                @endif
                <x-input.button size="feld" :label="$editingId ? __('Speichern') : __('Hinzufügen')" />
            </div>
        </form>

        @foreach ($kindLabels as $kind => $label)
            @continue(! isset($options[$kind]))
            <div class="border-b border-gray-100 px-5 pb-2 pt-4 last:border-b-0 dark:border-gray-700">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                @foreach ($options[$kind] as $option)
                    <div wire:key="option-{{ $option->id }}" @class([
                        'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-gray-100 py-2 last:border-b-0 dark:border-gray-700',
                        'opacity-50' => ! $option->active,
                    ])>
                        <div class="min-w-0">
                            <div class="text-gray-900 dark:text-gray-100">
                                {{ $option->name }}
                                @if ($option->needs_approval)
                                    <span class="ml-1 rounded-sm bg-amber-50 px-1.5 py-px text-[11px] text-amber-700 dark:bg-amber-900/20 dark:text-yellow-400">{{ __('Freigabe GF') }}</span>
                                @endif
                                @if ($option->assignee === 'customer')
                                    <span class="ml-1 rounded-sm bg-amber-50 px-1.5 py-px text-[11px] text-amber-700 dark:bg-amber-900/20 dark:text-yellow-400">{{ __('Kunde') }}</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-x-3 text-xs text-gray-500 dark:text-gray-400">
                                @if ($option->adGroup)<span class="font-mono">{{ $option->adGroup->name }}</span>@endif
                                @if ($option->detail)<span>{{ $option->detail }}</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="relative inline-flex cursor-pointer items-center" title="{{ __('Im Formular anbieten') }}">
                                <input type="checkbox" class="peer sr-only" @checked($option->active) wire:click="toggleActive({{ $option->id }})" aria-label="{{ __('Im Formular anbieten') }}">
                                <span class="{{ $toggle }}"></span>
                            </label>
                            <x-input.button type="button" color="gray" size="sm" wire:click="editOption({{ $option->id }})" :label="__('Bearbeiten')" />
                            <x-input.button type="button" color="gray" size="sm" wire:click="deleteOption({{ $option->id }})" wire:confirm="{{ __('Auswahl wirklich löschen?') }}" :label="__('Löschen')" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        @if ($options->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Noch keine Programme hinterlegt.') }}</div>
        @endif
    </x-panel>

    @foreach ($templates as $entry)
        @php($type = $entry['template']->type)
        <x-panel polster="keins">
            <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Schritte :name', ['name' => $entry['template']->name]) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Standard-Schritte gelten für alle Kunden und lassen sich hier abschalten. Eigene Schritte gelten nur für diesen Kunden.') }}</div>
            </div>

            <div class="px-5 py-2">
                @foreach ($entry['items'] as $item)
                    @php($off = in_array($item->id, $hidden, true))
                    <div wire:key="step-{{ $item->id }}" @class([
                        'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-l-2 border-b-gray-100 py-2 pl-3 last:border-b-0 dark:border-b-gray-700',
                        'border-l-gray-300 dark:border-l-gray-600' => $item->isStandard(),
                        'border-l-amber-500' => ! $item->isStandard(),
                    ])>
                        <div @class(['min-w-0', 'opacity-50 line-through' => $off])>
                            <div class="text-gray-900 dark:text-gray-100">{{ $item->title }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $item->section }} · {{ $item->assignee === 'customer' ? __('Kunde') : __('IT') }}
                                @unless ($item->isStandard()) · <span class="text-amber-600 dark:text-yellow-400">{{ __('nur dieser Kunde') }}</span> @endunless
                            </div>
                        </div>
                        @if ($item->isStandard())
                            <label class="relative inline-flex cursor-pointer items-center" title="{{ __('Für diesen Kunden verwenden') }}">
                                <input type="checkbox" class="peer sr-only" @checked(! $off) wire:click="toggleStandard({{ $item->id }})" aria-label="{{ __('Für diesen Kunden verwenden') }}">
                                <span class="{{ $toggle }}"></span>
                            </label>
                        @else
                            <x-input.button type="button" color="gray" size="sm" wire:click="deleteStep({{ $item->id }})" :label="__('Löschen')" />
                        @endif
                    </div>
                @endforeach
                <div class="py-2 pl-3 text-xs text-gray-500 dark:text-gray-400">
                    @if ($type === 'onboarding')
                        {{ __('Dazu kommt je ausgewähltem Laufwerk, Programm und Gerät ein eigener Punkt. Platzhalter im Text: {username}, {email}, {name}.') }}
                    @else
                        {{ __('Dazu kommen die Gruppen des AD-Benutzers und sein Postfach aus der Doku. Platzhalter im Text: {username}, {email}, {name}.') }}
                    @endif
                </div>
            </div>

            <form wire:submit="addStep('{{ $type }}')" class="grid grid-cols-1 gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 md:grid-cols-[10rem_minmax(0,1fr)_7rem_auto] dark:border-gray-700 dark:bg-gray-700/30">
                <div>
                    <x-input.label for="step-{{ $type }}-section" :value="__('Abschnitt')" />
                    <x-input.text id="step-{{ $type }}-section" list="sections-{{ $type }}" class="mt-1 block w-full" wire:model="steps.{{ $type }}.section" />
                    <datalist id="sections-{{ $type }}">
                        @foreach ($entry['items']->pluck('section')->unique() as $section)
                            <option value="{{ $section }}"></option>
                        @endforeach
                    </datalist>
                    <x-input.fehler feld="steps.{{ $type }}.section" />
                </div>
                <div>
                    <x-input.label for="step-{{ $type }}-title" :value="__('Schritt')" />
                    <x-input.text id="step-{{ $type }}-title" class="mt-1 block w-full" wire:model="steps.{{ $type }}.title" :placeholder="__('z. B. DATEV-Zugang beantragen')" />
                    <x-input.fehler feld="steps.{{ $type }}.title" />
                </div>
                <div>
                    <x-input.label for="step-{{ $type }}-assignee" :value="__('Zuständig')" />
                    <select id="step-{{ $type }}-assignee" wire:model="steps.{{ $type }}.assignee" class="{{ $select }}">
                        <option value="it">{{ __('IT') }}</option>
                        <option value="customer">{{ __('Kunde') }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <x-input.button size="feld" :label="__('Hinzufügen')" />
                </div>
            </form>
        </x-panel>
    @endforeach
</div>
