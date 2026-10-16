<div class="grid grid-cols-1 items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_24rem]">
    <form wire:submit="submit" class="min-w-0">
        <x-panel polster="keins">
            <div class="space-y-4 border-b border-gray-100 p-5 dark:border-gray-700">
                <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Wer fängt an?') }}</div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <x-input.label for="first_name" :value="__('Vorname')" />
                        <x-input.text id="first_name" feld="first_name" class="mt-1 block w-full" wire:model.live.debounce.300ms="first_name" required />
                        <x-input.fehler feld="first_name" />
                    </div>
                    <div>
                        <x-input.label for="last_name" :value="__('Nachname')" />
                        <x-input.text id="last_name" feld="last_name" class="mt-1 block w-full" wire:model.live.debounce.300ms="last_name" required />
                        <x-input.fehler feld="last_name" />
                    </div>
                    <div>
                        <x-input.label for="reference_date" :value="__('Erster Arbeitstag')" />
                        <x-input.text id="reference_date" type="date" feld="reference_date" class="mt-1 block w-full" wire:model="reference_date" required />
                        <x-input.fehler feld="reference_date" />
                    </div>
                    <div>
                        <x-input.label for="username" :value="__('Benutzername (optional)')" />
                        <x-input.text id="username" feld="username" class="mt-1 block w-full font-mono" wire:model.live.debounce.300ms="username" :placeholder="$suggestedUsername" />
                        <x-input.fehler feld="username" />
                    </div>
                    <div>
                        <x-input.label for="email" :value="__('E-Mail-Adresse (optional)')" />
                        <x-input.text id="email" type="email" feld="email" class="mt-1 block w-full font-mono" wire:model.live.debounce.300ms="email" :placeholder="$suggestedEmail ?? ''" />
                        <x-input.fehler feld="email" />
                    </div>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Benutzername und E-Mail leer lassen, dann gilt der Vorschlag. Die IT kann beides später anpassen.') }}</div>
            </div>

            @if ($shares->isEmpty() && $choices->every->isEmpty())
                <div class="border-b border-gray-100 p-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    {{ __('Für diesen Kunden sind noch keine Laufwerke oder Programme zur Auswahl hinterlegt.') }}
                    @can('checklist_manage')
                        <a href="{{ route('checklist.settings', $customer) }}" class="text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Jetzt einrichten') }}</a>
                    @endcan
                </div>
            @endif

            @if ($shares->isNotEmpty())
                <div class="space-y-3 border-b border-gray-100 p-5 dark:border-gray-700">
                    <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Netzlaufwerke') }}</div>
                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        @foreach ($shares as $drive)
                            <div wire:key="drive-{{ $drive->id }}" class="grid grid-cols-1 items-center gap-2 border-b border-gray-100 px-3 py-2 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto] dark:border-gray-700">
                                <div class="min-w-0">
                                    <div class="text-gray-900 dark:text-gray-100">
                                        <span class="font-mono font-semibold">{{ $drive->drive_letter ? $drive->drive_letter.':' : '' }}</span> {{ $drive->name }}
                                        @if ($drive->sensitive)
                                            <span class="ml-1 text-xs text-amber-600 dark:text-yellow-400">{{ __('Freigabe Geschäftsführung') }}</span>
                                        @endif
                                    </div>
                                    @if ($drive->path)
                                        <div class="break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $drive->path }}</div>
                                    @endif
                                </div>
                                <div class="inline-flex justify-self-start overflow-hidden rounded-lg border border-gray-300 text-xs font-semibold sm:justify-self-end dark:border-gray-600" role="radiogroup" aria-label="{{ $drive->name }}">
                                    @foreach (['' => __('Kein'), 'r' => __('Lesen'), 'rw' => __('Schreiben')] as $value => $label)
                                        <label class="cursor-pointer border-l border-gray-300 first:border-l-0 dark:border-gray-600">
                                            <input type="radio" class="peer sr-only" name="drive-{{ $drive->id }}" value="{{ $value }}" wire:model.live="drives.{{ $drive->id }}">
                                            <span class="block bg-white px-3 py-1.5 text-gray-700 peer-checked:bg-cerulean-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-cerulean-500 dark:bg-gray-700 dark:text-gray-200 dark:peer-checked:bg-cerulean-600 dark:peer-checked:text-white">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach (['app' => __('Programme und Zugänge')] as $kind => $heading)
                @if ($choices[$kind]->isNotEmpty())
                    <div class="space-y-3 border-b border-gray-100 p-5 dark:border-gray-700">
                        <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ $heading }}</div>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($choices[$kind] as $option)
                                <label wire:key="option-{{ $option->id }}" class="flex h-16 cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 transition-colors hover:border-cerulean-300 has-[:checked]:border-cerulean-600 has-[:checked]:bg-cerulean-50 dark:border-gray-700 dark:has-[:checked]:border-cerulean-400 dark:has-[:checked]:bg-cerulean-900/20">
                                    <input type="checkbox" value="{{ $option->id }}" wire:model.live="options" class="h-4 w-4 shrink-0 rounded-sm border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                    <span class="min-w-0">
                                        <span class="block truncate text-gray-900 dark:text-gray-100">{{ $option->name }}</span>
                                        @if ($option->detail)
                                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $option->detail }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <div class="space-y-2 p-5">
                <x-input.label for="note" :value="__('Hinweis an die IT')" />
                <textarea id="note" rows="3" wire:model="note" placeholder="{{ __('z. B. Homeoffice, besondere Programme, abweichende Rechte') }}"
                    class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"></textarea>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-700/30">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Nach dem Absenden arbeitet die IT die Liste ab. Den Stand sehen Sie unter Checklisten.') }}</span>
                <x-input.button :label="__('An die IT senden')" wire:loading.attr="disabled" />
            </div>
        </x-panel>
    </form>

    <x-panel polster="keins" class="2xl:sticky 2xl:top-20">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-700">
            <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Daraus entsteht') }}</div>
            <span class="rounded-sm border border-gray-200 bg-gray-50 px-1.5 py-px text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ trans_choice(':count Punkt|:count Punkte', $previewCount) }}</span>
        </div>
        <dl class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-x-3 gap-y-1 border-b border-gray-100 px-5 py-3 text-sm dark:border-gray-700">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Benutzer') }}</dt>
            <dd class="break-all font-mono text-xs leading-5 text-gray-900 dark:text-gray-100">{{ $username ?: '–' }}</dd>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('E-Mail') }}</dt>
            <dd class="break-all font-mono text-xs leading-5 text-gray-900 dark:text-gray-100">{{ $email ?: '–' }}</dd>
        </dl>
        @if ($approvals)
            <div class="border-b border-gray-100 px-5 py-2 text-sm text-amber-600 dark:border-gray-700 dark:text-yellow-400">
                {{ trans_choice(':count Laufwerk braucht die Freigabe der Geschäftsführung|:count Laufwerke brauchen die Freigabe der Geschäftsführung', $approvals) }}
            </div>
        @endif
        @foreach ($preview as $section => $items)
            <div class="border-b border-gray-100 px-5 py-3 last:border-b-0 dark:border-gray-700">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $section }}</div>
                @foreach ($items as $item)
                    <div class="flex items-start justify-between gap-3 py-1 text-sm">
                        <span class="text-gray-800 dark:text-gray-200">{{ $item['title'] }}</span>
                        <span class="shrink-0 text-xs {{ $item['assignee'] === 'customer' ? 'text-amber-600 dark:text-yellow-400' : 'text-cerulean-600 dark:text-cerulean-400' }}">{{ $item['assignee'] === 'customer' ? __('Kunde') : __('IT') }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </x-panel>
</div>
