{{-- Everything the network scan knows about one finding, as label/value
     pairs. Shared by the new and the ignored findings. Values the device did
     not report are left out instead of showing a column of dashes. --}}
@php
    $mac = str_starts_with((string) $fund->identifier, 'mac:') ? substr($fund->identifier, 4) : null;
    $angaben = [
        __('Typ') => $fund->typeLabel(),
        __('Name im Gerät') => $fund->name,
        __('Hersteller') => $fund->manufacturer,
        __('Modell') => $fund->model,
        __('Seriennummer') => $fund->serial,
        __('MAC-Adresse') => $mac,
        __('Firmware') => $fund->firmware,
        __('IP-Adresse') => $fund->ip,
        __('Netz') => $fund->network
            ? collect([\App\Models\AgentInstallation::cidrOf($fund->network), $fund->network->label(true), $fund->network->site?->name])->filter()->join(' · ')
            : null,
        __('Gefunden von') => $fund->agentInstallation?->hostname,
        __('Zuerst gesehen') => \App\Support\LocalTime::display($fund->created_at),
        __('Zuletzt gesehen') => \App\Support\LocalTime::display($fund->last_seen_at),
    ];
@endphp
<dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($angaben as $bezeichnung => $wert)
        @if (filled($wert))
            <div class="min-w-0">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $bezeichnung }}</dt>
                <dd class="break-words text-gray-800 dark:text-gray-100">{{ $wert }}</dd>
            </div>
        @endif
    @endforeach
</dl>
