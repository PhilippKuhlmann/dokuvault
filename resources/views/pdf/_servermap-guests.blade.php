{{-- The guests of a host as a table, VMs and containers apart. Without the
     right to see VMs only their number. --}}
@if (! $list['visible'])
    <div class="smap-line">{{ trans_choice(':count Gast|:count Gäste', $list['count']) }}</div>
@else
    <table class="ports smap-guests">
        <tr>
            <th style="width: 24%">{{ __('Name') }}</th>
            <th style="width: 10%">{{ __('Art') }}</th>
            <th style="width: 24%">{{ __('Betriebssystem') }}</th>
            <th style="width: 20%">{{ __('Ressourcen') }}</th>
            <th style="width: 12%">{{ __('Adresse') }}</th>
            <th style="width: 10%">{{ __('Hinweis') }}</th>
        </tr>
        @foreach ($list['groups'] as $group)
            @foreach ($group['guests'] as $guest)
                <tr @class(['smap-stopped' => $guest['stopped']])>
                    <td>{{ $guest['name'] }}</td>
                    <td>{{ $group['kind'] === 'lxc' ? __('Container') : __('VM') }}</td>
                    <td>{{ $guest['os'] }}</td>
                    <td>{{ implode(' · ', $guest['specs']) }}</td>
                    <td class="mono">{{ $guest['address'] }}</td>
                    <td>{{ implode(', ', array_filter([
                        $guest['stopped'] ? __('aus') : null,
                        $guest['backup'] === false ? __('kein Backup') : null,
                    ])) }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
@endif
