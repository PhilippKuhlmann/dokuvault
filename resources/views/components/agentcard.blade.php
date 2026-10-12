@props(['device', 'title', 'werte' => [], 'listen' => []])

{{-- What an agent reported about a server or VM (agent_details): read only,
     replaced by every run. The note at the end says when - so it is clear at
     a glance what came by itself and what someone entered. Nothing at all
     without a report. --}}
@if ($device->agent_reported_at)
    <x-minitablecard :title="$title" :array="$werte" />

    @foreach ($listen as $titel => $zeilen)
        @if (count($zeilen))
            <div class="w-full mb-5 break-inside-avoid">
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $titel }}</div>
                <table class="w-full text-sm dark:text-gray-100">
                    @foreach ($zeilen as [$links, $rechts])
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ $links }}</td>
                            <td class="py-1 break-all">{{ $rechts }}</td>
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
