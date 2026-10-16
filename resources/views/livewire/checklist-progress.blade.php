@php
    $total = $checklist->items->count();
    $done = $checklist->items->whereNotNull('done_at')->count();
    $waiting = $checklist->items->filter->awaitsApproval()->count();
@endphp

<div class="space-y-5">
    <x-panel polster="keins">
        <dl class="grid grid-cols-1 gap-x-10 px-5 py-4 text-sm md:grid-cols-2 xl:grid-cols-3">
            @foreach (array_filter([
                ($checklist->isOnboarding() ? __('Arbeitsbeginn') : __('Letzter Tag')) => $checklist->reference_date->format('d.m.Y'),
                __('Benutzername') => $canManage && $checklist->isOnboarding() ? null : $checklist->username,
                __('E-Mail') => $canManage && $checklist->isOnboarding() ? null : $checklist->email,
                __('Gemeldet von') => $checklist->requester ? $checklist->requester->name.', '.$checklist->created_at->format('d.m.Y') : $checklist->created_at->format('d.m.Y'),
            ]) as $label => $value)
                <div class="grid grid-cols-[9rem_minmax(0,1fr)] gap-2 border-b border-gray-100 py-1.5 dark:border-gray-700">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="break-words text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($canManage && $checklist->isOnboarding())
            <form wire:submit="saveAccount" class="grid grid-cols-1 items-end gap-3 border-t border-gray-100 px-5 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] dark:border-gray-700">
                <div>
                    <x-input.label for="account-username" :value="__('Benutzername')" />
                    <x-input.text id="account-username" feld="username" class="mt-1 block w-full font-mono" wire:model="username" />
                    <x-input.fehler feld="username" />
                </div>
                <div>
                    <x-input.label for="account-email" :value="__('E-Mail-Adresse')" />
                    <x-input.text id="account-email" type="email" feld="email" class="mt-1 block w-full font-mono" wire:model="email" />
                    <x-input.fehler feld="email" />
                </div>
                <x-input.button size="feld" :label="__('Speichern')" />
            </form>
        @endif

        @if ($checklist->isOnboarding() && ($requestedDrives->isNotEmpty() || $requestedOptions->isNotEmpty() || $checklist->note))
            <div class="space-y-2 border-t border-gray-100 px-5 py-4 text-sm dark:border-gray-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Antrag') }}</div>
                @if ($requestedDrives->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="w-28 text-gray-500 dark:text-gray-400">{{ __('Laufwerke') }}</span>
                        @foreach ($requestedDrives as $entry)
                            <span @class([
                                'rounded-sm border px-1.5 py-px text-xs',
                                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-yellow-400' => $entry['share']->sensitive,
                                'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200' => ! $entry['share']->sensitive,
                            ])>{{ $entry['share']->label() }} · {{ strtoupper($entry['level']) }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($requestedOptions->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="w-28 text-gray-500 dark:text-gray-400">{{ __('Weiteres') }}</span>
                        @foreach ($requestedOptions as $option)
                            <span class="rounded-sm border border-gray-200 bg-gray-50 px-1.5 py-px text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ $option->name }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($checklist->note)
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-gray-700 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300">„{{ $checklist->note }}“</div>
                @endif
            </div>
        @endif

        <div class="space-y-1.5 border-t border-gray-100 px-5 py-3 dark:border-gray-700">
            <div class="flex flex-wrap justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ __(':done von :total erledigt', ['done' => $done, 'total' => $total]) }}</span>
                <span class="flex gap-3">
                    @if ($waiting)
                        <span class="text-amber-600 dark:text-yellow-400">{{ trans_choice(':count Freigabe offen|:count Freigaben offen', $waiting) }}</span>
                    @endif
                    @if ($checklist->completed_at)
                        <span class="text-green-600 dark:text-green-400">✓ {{ __('abgeschlossen am') }} {{ $checklist->completed_at->format('d.m.Y') }}</span>
                    @endif
                </span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-full rounded-full bg-cerulean-600 transition-all" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></div>
            </div>
        </div>
    </x-panel>

    @if ($canManage && $checklist->username && ($scriptPlan['items']->isNotEmpty() || $scriptPlan['waiting']->isNotEmpty() || $checklist->script_reported_at))
        @php
            $planned = $scriptPlan['items'];
            $groupCount = $planned->whereIn('action', ['add_group', 'remove_group'])->count();
        @endphp
        <x-panel polster="keins">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div class="min-w-0 space-y-1 text-sm">
                    <div class="text-lg font-CoconPro text-chathams-blue-800 dark:text-gray-100">{{ __('Im AD erledigen') }}</div>
                    @if ($planned->isNotEmpty())
                        <div class="text-gray-600 dark:text-gray-300">
                            {{ __('Das PowerShell-Skript erledigt :count offene Punkte', ['count' => $planned->count()]) }}:
                            {{ collect([
                                $planned->contains('action', 'create_ad_user') ? __('Benutzer :name anlegen', ['name' => $checklist->username]) : null,
                                $planned->contains('action', 'disable_ad_user') ? __('Benutzer :name deaktivieren', ['name' => $checklist->username]) : null,
                                $planned->contains('action', 'reset_password') ? __('Kennwort zurücksetzen') : null,
                                $groupCount ? trans_choice(':count Gruppe|:count Gruppen', $groupCount) : null,
                            ])->filter()->implode(', ') }}.
                            {{ __('Danach meldet es zurück und hakt die Punkte hier ab.') }}
                        </div>
                    @else
                        <div class="text-gray-500 dark:text-gray-400">{{ __('Für das Skript ist nichts mehr offen.') }}</div>
                    @endif
                    @if ($scriptPlan['waiting']->isNotEmpty())
                        <div class="text-amber-600 dark:text-yellow-400">{{ trans_choice(':count Punkt wartet auf Freigabe und ist nicht im Skript.|:count Punkte warten auf Freigabe und sind nicht im Skript.', $scriptPlan['waiting']->count()) }}</div>
                    @endif
                    @if ($checklist->script_reported_at)
                        <div class="text-green-600 dark:text-green-400">✓ {{ __('Zuletzt ausgeführt am :date', ['date' => $checklist->script_reported_at->format('d.m.Y H:i')]) }}</div>
                    @endif
                </div>
                @if ($planned->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-4">
                    @if ($checklist->isOnboarding() && $planned->contains('action', 'create_ad_user'))
                        @if ($fixedPassword)
                            <span class="text-sm text-gray-500 dark:text-gray-400" title="{{ __('Festes Start-Kennwort des Kunden') }}">✓ {{ __('Kennwortänderung bei der ersten Anmeldung (festes Start-Kennwort)') }}</span>
                        @else
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" wire:model.live="forcePasswordChange" class="h-4 w-4 rounded-sm border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                                {{ __('Kennwort bei der ersten Anmeldung ändern lassen') }}
                            </label>
                        @endif
                    @endif
                    <a href="{{ route('checklist.ad-script', [$checklist->customer, $checklist]) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-cerulean-600 px-4 py-2 text-sm font-DINPro-bold text-white shadow-xs hover:bg-cerulean-700 focus:outline-hidden focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        {{ __('PowerShell-Skript herunterladen') }}
                    </a>
                    </div>
                @endif
            </div>
        </x-panel>
    @endif

    <x-panel polster="keins">
        @foreach ($sections as $section => $items)
            <div class="border-b border-gray-100 px-5 pb-2 pt-4 last:border-b-0 dark:border-gray-700">
                <div class="mb-1 flex items-baseline justify-between">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $section }}</div>
                    <div class="text-xs text-gray-400">{{ $items->whereNotNull('done_at')->count() }}/{{ $items->count() }}</div>
                </div>

                @foreach ($items as $item)
                    <div wire:key="item-{{ $item->id }}" class="grid grid-cols-[1.5rem_minmax(0,1fr)_auto] items-start gap-x-3 gap-y-0.5 border-b border-gray-100 py-2 last:border-b-0 dark:border-gray-700">
                        <input type="checkbox" id="item-{{ $item->id }}"
                            class="mt-0.5 h-4 w-4 rounded-sm border-gray-300 text-cerulean-600 focus:ring-cerulean-500 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700"
                            @checked($item->isDone())
                            @disabled(! $canManage || $item->awaitsApproval())
                            wire:click="toggle({{ $item->id }})">
                        <label for="item-{{ $item->id }}" @class([
                            'min-w-0',
                            'text-gray-400 line-through dark:text-gray-500' => $item->isDone(),
                            'text-gray-900 dark:text-gray-100' => ! $item->isDone(),
                        ])>{{ $item->title }}</label>
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            <span @class([
                                'rounded-sm px-1.5 py-px text-[11px] font-medium',
                                'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-yellow-400' => $item->assignee === 'customer',
                                'bg-cerulean-50 text-cerulean-700 dark:bg-cerulean-900/30 dark:text-cerulean-300' => $item->assignee !== 'customer',
                            ])>{{ $item->assignee === 'customer' ? __('Kunde') : __('IT') }}</span>
                        </div>

                        @if ($item->reference || $item->hint || $item->needs_approval || $item->isDone() || $item->action)
                            <div class="col-start-2 col-span-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($item->reference)
                                    <span class="rounded-sm bg-gray-100 px-1.5 py-px font-mono text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $item->reference }}</span>
                                @endif
                                @if ($item->hint)
                                    <span class="break-all">{{ $item->hint }}</span>
                                @endif
                                @if ($item->needs_approval)
                                    @if ($item->approved_at)
                                        <span class="text-green-600 dark:text-green-400">✓ {{ __('freigegeben von :name am :date', ['name' => $item->approvedBy?->name ?? '?', 'date' => $item->approved_at->format('d.m.Y')]) }}</span>
                                    @else
                                        <span class="text-amber-600 dark:text-yellow-400">{{ __('wartet auf Freigabe der Geschäftsführung') }}</span>
                                        @if ($canApprove)
                                            <x-input.button type="button" size="sm" wire:click="approve({{ $item->id }})" :label="__('Freigeben')" />
                                        @endif
                                    @endif
                                @endif
                                @if ($item->action && ! $item->isDone())
                                    <span class="text-cerulean-600 dark:text-cerulean-400">{{ __('per Skript') }}</span>
                                @endif
                                @if ($item->isDone())
                                    <span>{{ __('erledigt von :name am :date', ['name' => $item->doneBy?->name ?? '?', 'date' => $item->done_at->format('d.m.Y')]) }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </x-panel>
</div>
