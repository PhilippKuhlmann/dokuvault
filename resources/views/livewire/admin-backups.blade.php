<div class="p-3 sm:p-5 space-y-4">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Backups') }}</div>

    {{-- How the last run of each backup ended - before "only problems"
         filters, so the counts stay put when it is ticked. --}}
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span @class(['rounded-full px-3 py-1 font-semibold', 'bg-red-600 text-white' => $counts['failed'], 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! $counts['failed']])>{{ trans_choice(':count fehlgeschlagen|:count fehlgeschlagen', $counts['failed']) }}</span>
        <span @class(['rounded-full px-3 py-1 font-semibold', 'bg-amber-400 text-gray-900' => $counts['warning'], 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => ! $counts['warning']])>{{ trans_choice(':count mit Warnung|:count mit Warnung', $counts['warning']) }}</span>
        <span class="rounded-full bg-green-600 px-3 py-1 font-semibold text-white">{{ trans_choice(':count erfolgreich|:count erfolgreich', $counts['ok']) }}</span>
    </div>

    <x-panel polster="eng">
        <div class="flex flex-wrap items-start gap-4">
            <div>
                <x-input.label :value="__('Kunde')" />
                <x-input.select name="kunde" wire:model.live="kunde" class="mt-1">
                    <option value="">{{ __('Alle Kunden') }}</option>
                    @foreach ($kunden as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-input.select>
            </div>
            {{-- A setting of the installation, saved on change - the
                 customer's backup card shows the same period. --}}
            <div>
                <x-input.label for="days" :value="__('Zeitraum')" />
                <x-input.select name="days" id="days" wire:model.live="days" class="mt-1">
                    @foreach (\App\Models\Setting::BACKUP_DAY_OPTIONS as $wert)
                        <option value="{{ $wert }}">{{ trans_choice(':count Tag|:count Tage', $wert) }}</option>
                    @endforeach
                </x-input.select>
            </div>
            {{-- Retention: the oldest runs beyond this number are deleted,
                 also right away when the number is lowered. --}}
            <div>
                <x-input.label for="aufbewahrung" :value="__('Höchstens speichern')" />
                <div class="mt-1 flex items-center gap-2">
                    <x-input.field id="aufbewahrung" type="number" min="10" max="10000"
                        wire:model.live.debounce.800ms="aufbewahrung" class="w-28" />
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Läufe je Backup') }}</span>
                </div>
                <x-input.fehler feld="aufbewahrung" />
            </div>
            <label class="flex cursor-pointer select-none items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" wire:model.live="nurProbleme"
                    class="h-4 w-4 rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                {{ __('Nur mit Fehler oder Warnung') }}
            </label>
        </div>
    </x-panel>

    {{-- Legend: what the colours of the squares mean. --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-green-600"></span>{{ __('Erfolgreich') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-amber-400"></span>{{ __('Mit Warnungen') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-red-600"></span>{{ __('Fehlgeschlagen') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-gray-200 dark:bg-gray-600"></span>{{ __('kein Lauf') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm border border-dashed border-gray-300 dark:border-gray-600"></span>{{ __('noch nicht erfasst') }}</span>
        <span class="text-gray-400 dark:text-gray-500">{{ __('Je Tag zählt der schlechteste Lauf, rechts ist heute.') }}</span>
    </div>

    <x-panel polster="keins" class="overflow-x-auto">
        <table class="w-full min-w-176 text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Kunde') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Backup') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Letzte :tage Tage', ['tage' => $days]) }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Tage erfolgreich') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Letzter Erfolg') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($backups as $backup)
                    @php
                        $cells = \App\Support\BackupRunDays::for($backup, $days);
                        // Of the days that had a run: how many went through.
                        $ran = collect($cells)->whereIn('state', ['ok', 'warning', 'failed'])->count();
                        $ok = collect($cells)->where('state', 'ok')->count();
                    @endphp
                    <tr wire:key="backup-{{ $backup->id }}" class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100">
                            @if ($backup->customer)
                                <a href="{{ route('backup.index', [$backup->customer, 'highlight' => $backup->id]) }}" class="hover:text-cerulean-600">{{ $backup->customer->name }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="text-gray-900 dark:text-gray-100">{{ $backup->name }}</div>
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $backup->software }}@if ($backup->destination) · {{ __('nach') }} {{ $backup->destination }}@endif
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <x-backup-verlauf :backup="$backup" :cells="$cells" large />
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5">
                            @if ($ran > 0)
                                <span @class([
                                    'font-medium',
                                    'text-green-700 dark:text-green-400' => $ok === $ran,
                                    'text-amber-600 dark:text-amber-400' => $ok < $ran && $ok > 0,
                                    'text-red-600 dark:text-red-400' => $ok === 0,
                                ])>{{ $ok }} / {{ $ran }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5">
                            {{ $backup->last_success ? \Illuminate\Support\Carbon::parse($backup->last_success)->format('d.m.Y') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __('Keine gemeldeten Backups. Backups erscheinen hier, sobald ein Agent sie meldet (Veeam, Windows Server-Sicherung, Proxmox).') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</div>
