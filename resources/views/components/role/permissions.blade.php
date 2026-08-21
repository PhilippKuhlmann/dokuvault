@props([
    'matrix' => [],
    'others' => [],
    'actions' => [],
    'adminRechte' => [],
    'selected' => [],
])

@php
    $cb = 'perm-cb w-4 h-4 text-cerulean-600 bg-gray-100 border-gray-300 rounded focus:ring-cerulean-500 focus:ring-2 dark:bg-gray-700 dark:border-gray-600 dark:ring-offset-gray-800';
@endphp

<div class="mt-4 w-full" data-perm-root>
    <div class="flex items-center justify-between mb-2">
        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Berechtigungen') }}</div>
        <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 cursor-pointer select-none">
            <input type="checkbox"
                onchange="this.closest('[data-perm-root]').querySelectorAll('.perm-cb').forEach(function(c){ c.checked = this.checked; }, this)"
                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            {{ __('Alle auswählen') }}
        </label>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="text-left px-4 py-2 font-semibold">{{ __('Bereich') }}</th>
                    @foreach ($actions as $label)
                        <th class="px-3 py-2 font-semibold text-center whitespace-nowrap">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @foreach ($matrix as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-1.5 whitespace-nowrap">
                            <button type="button" title="{{ __('Ganze Zeile umschalten') }}"
                                onclick="var cbs = this.closest('tr').querySelectorAll('.perm-cb'); var all = Array.prototype.every.call(cbs, function(c){ return c.checked; }); cbs.forEach(function(c){ c.checked = !all; })"
                                class="text-gray-800 dark:text-gray-200 hover:text-cerulean-600 dark:hover:text-cerulean-400 font-medium">
                                {{ $row['label'] }}
                            </button>
                        </td>
                        @foreach ($actions as $action => $label)
                            @php $perm = $row['perms'][$action] ?? null; @endphp
                            <td class="px-3 py-1.5 text-center">
                                @if ($perm)
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                        {{ in_array($perm->id, $selected) ? 'checked' : '' }}
                                        aria-label="{{ $perm->description }}" class="{{ $cb }}">
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Der Admin-Bereich steht fuer sich, mit Rahmen und eigener Farbe.
         Diese Rechte greifen nicht auf die Daten eines Kunden, sondern auf die
         Installation: Wer sie vergibt, soll das nicht zwischen zwei
         Geraetezeilen tun. --}}
    @if (count($adminRechte))
        <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50/60 p-4 dark:border-amber-800/70 dark:bg-amber-900/10">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-300">
                        <x-svg.settings class="h-4 w-4" />
                        {{ __('Admin-Bereich') }}
                    </div>
                    <p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-400/80">
                        {{ __('Gilt für die ganze Installation, nicht für einen einzelnen Kunden.') }}
                    </p>
                </div>
                <label class="flex shrink-0 cursor-pointer select-none items-center gap-2 text-xs text-amber-800 dark:text-amber-400">
                    <input type="checkbox"
                        onchange="this.closest('[data-admin-block]').querySelectorAll('.perm-cb').forEach(function(c){ c.checked = this.checked; }, this)"
                        class="h-4 w-4 rounded border-amber-300 dark:border-amber-700 dark:bg-gray-700">
                    {{ __('Alle auswählen') }}
                </label>
            </div>

            <div data-admin-block class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($adminRechte as $perm)
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                            {{ in_array($perm->id, $selected) ? 'checked' : '' }} class="{{ $cb }}">
                        {{ __($perm->description) }}
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    @if (count($others))
        <div class="mt-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Sonstige Rechte') }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach ($others as $perm)
                    <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                            {{ in_array($perm->id, $selected) ? 'checked' : '' }} class="{{ $cb }}">
                        {{ $perm->description }}
                    </label>
                @endforeach
            </div>
        </div>
    @endif
</div>
