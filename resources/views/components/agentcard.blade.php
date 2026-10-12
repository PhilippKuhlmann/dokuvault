@props(['device', 'bloecke' => []])

{{-- What an agent reported about a server or VM (agent_details): read only,
     replaced by every run. The note at the end says when - so it is clear at
     a glance what came by itself and what someone entered. Nothing at all
     without a report.

     bloecke: title => rows; a row is [label, value] or [label, value, bar].
     bar: ['pct' => share in percent, 'dazu' => a second, paler share after
     it (assigned but not running), 'warnen' => amber from 80 %, red from
     90 %]. Rows without a value and blocks without rows are left out, as in
     x-minitablecard. --}}
@if ($device->agent_reported_at)
    @foreach ($bloecke as $titel => $zeilen)
        @php
            $zeilen = array_values(array_filter($zeilen, fn ($z) => filled($z[1] ?? null)));
        @endphp
        @if ($zeilen)
            <div class="w-full mb-5 break-inside-avoid">
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $titel }}</div>
                <table class="w-full text-sm dark:text-gray-100">
                    @foreach ($zeilen as $zeile)
                        @php
                            $balken = $zeile[2] ?? null;
                            $pct = $balken ? max(0, min(100, (int) round($balken['pct']))) : null;
                            $dazu = $balken ? max(0, min(100 - $pct, (int) round($balken['dazu'] ?? 0))) : 0;
                            $stufe = match (true) {
                                ! ($balken['warnen'] ?? false) => 'normal',
                                $pct >= 90 => 'rot',
                                $pct >= 80 => 'gelb',
                                default => 'normal',
                            };
                        @endphp
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ $zeile[0] }}</td>
                            <td class="py-1 break-all">
                                @if ($balken && ($balken['warnen'] ?? false))
                                    <div class="flex justify-between gap-2">
                                        <span>{{ $zeile[1] }}</span>
                                        <span @class([
                                            'text-gray-500 dark:text-gray-400' => $stufe === 'normal',
                                            'text-amber-600 dark:text-amber-400' => $stufe === 'gelb',
                                            'text-red-600 dark:text-red-400' => $stufe === 'rot',
                                        ])>{{ $pct }} %</span>
                                    </div>
                                @else
                                    {{ $zeile[1] }}
                                @endif

                                @if ($balken)
                                    <div class="mt-1.5 mb-0.5 flex h-1 overflow-hidden rounded-sm bg-gray-200 dark:bg-gray-700">
                                        <div style="width: {{ $pct }}%" @class([
                                            'bg-cerulean-500' => $stufe === 'normal',
                                            'bg-amber-500' => $stufe === 'gelb',
                                            'bg-red-500' => $stufe === 'rot',
                                        ])></div>
                                        @if ($dazu)
                                            <div style="width: {{ $dazu }}%" class="bg-cerulean-500/35"></div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endforeach

    <p class="w-full mb-5 text-xs text-gray-500 dark:text-gray-400">
        {{ __('Vom Agent gemeldet am :datum', ['datum' => \App\Support\Zeit::anzeigen($device->agent_reported_at)]) }}
    </p>
@endif
