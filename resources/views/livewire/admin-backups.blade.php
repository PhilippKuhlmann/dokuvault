<div class="p-3 sm:p-5 space-y-4">
    <div class="text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Backups') }}</div>

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
            {{-- A setting of the installation, saved on change. --}}
            <div>
                <x-input.label for="anzahl" :value="__('Letzte Läufe')" />
                <x-input.select name="anzahl" id="anzahl" wire:model.live="anzahl" class="mt-1">
                    @foreach ([5, 10, 20, 30, 50] as $wert)
                        <option value="{{ $wert }}">{{ $wert }}</option>
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

    <x-panel polster="keins" class="overflow-x-auto">
        <table class="w-full min-w-176 text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Kunde') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Backup') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Letzte :anzahl Läufe', ['anzahl' => $anzahl]) }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Erfolgreich') }}</th>
                    <th class="px-4 py-2.5 font-semibold">{{ __('Letzter Erfolg') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($backups as $backup)
                    @php($ok = $backup->recentRuns->where('status', 'ok')->count())
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
                            <x-backup-verlauf :runs="$backup->recentRuns" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5">
                            @if ($backup->recentRuns->isNotEmpty())
                                <span @class([
                                    'font-medium',
                                    'text-green-700 dark:text-green-400' => $ok === $backup->recentRuns->count(),
                                    'text-amber-600 dark:text-amber-400' => $ok < $backup->recentRuns->count() && $ok > 0,
                                    'text-red-600 dark:text-red-400' => $ok === 0,
                                ])>{{ $ok }} / {{ $backup->recentRuns->count() }}</span>
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
