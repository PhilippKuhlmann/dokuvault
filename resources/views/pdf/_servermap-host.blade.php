{{-- One host of the PDF server map: what it is built of on two lines,
     its guests below. --}}
<div class="smap-host">
    <div class="smap-host-title">
        <span class="smap-kind">{{ $host['type'] }}</span>
        {{ $host['name'] }}
    </div>
    @if ($hostLine($host))
        <div class="smap-line">{{ implode(' · ', $hostLine($host)) }}</div>
    @endif
    @if ($host['services'])
        <div class="smap-line">{{ __('Dienste') }}: {{ implode(', ', $host['services']) }}</div>
    @endif
    @if ($host['storages'])
        <div class="smap-line">{{ __('Speicher') }}: {{ $storageLine($host['storages']) }}</div>
    @endif
    @if ($host['assigned'])
        <div class="smap-line">{{ $host['assigned']['title'] }}</div>
    @endif
    @if ($host['guests'])
        @include('pdf._servermap-guests', ['list' => $host['guests']])
    @endif
</div>
