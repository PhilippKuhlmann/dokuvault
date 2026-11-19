{{-- The server map for the PDF: the same tree as on the page (ServerMap),
     drawn as indented tables - DomPDF knows neither flex nor grid. Cluster,
     below it its hosts, below each host its guests; all guests, folded
     nothing, the sheet is read through. --}}
@php
    $hostLine = fn (array $host) => array_values(array_filter([
        $host['model'],
        $host['os'],
        $host['cpu'],
        $host['facts'] ? implode(' · ', $host['facts']) : null,
        $host['address'],
    ]));
    $storageLine = fn (array $storages) => implode(' · ', array_map(fn ($st) => $st['name'].' '.$st['size'], $storages));
@endphp

@foreach ($sites as $entry)
    @continue(! $entry['columns'] && ! $entry['standalone'] && ! $entry['unhosted'])

    <div class="smap-site">{{ $entry['site']->name }}</div>

    @foreach ($entry['columns'] as $column)
        @if ($column['cluster'])
            <div class="smap-cluster">
                <span class="smap-kind">{{ $column['cluster']['type'] }}</span>
                {{ $column['cluster']['name'] }}
                <span class="smap-facts">{{ implode(' · ', $column['cluster']['facts']) }}</span>
                @if ($column['cluster']['storages'])
                    <div class="smap-line">{{ __('Speicher') }}: {{ $storageLine($column['cluster']['storages']) }}</div>
                @endif
            </div>
        @endif

        <div @class(['smap-hosts' => $column['cluster']])>
            @foreach ($column['hosts'] as $host)
                @include('pdf._servermap-host', ['host' => $host, 'hostLine' => $hostLine, 'storageLine' => $storageLine])
            @endforeach

            @if ($column['loose'])
                <div class="smap-host">
                    <div class="smap-host-title">{{ __('Host unbekannt') }}</div>
                    @include('pdf._servermap-guests', ['list' => $column['loose']])
                </div>
            @endif
        </div>
    @endforeach

    @if ($entry['standalone'])
        <div class="smap-caption">{{ __('Server ohne Gäste') }}</div>
        @foreach ($entry['standalone'] as $host)
            @include('pdf._servermap-host', ['host' => $host, 'hostLine' => $hostLine, 'storageLine' => $storageLine])
        @endforeach
    @endif

    @if ($entry['unhosted'])
        <div class="smap-caption">{{ __('VMs ohne Host') }}</div>
        <div class="smap-host">
            @include('pdf._servermap-guests', ['list' => $entry['unhosted']])
        </div>
    @endif
@endforeach
