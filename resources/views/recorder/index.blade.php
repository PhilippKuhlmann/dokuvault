<x-app-layout :$customer>

    <x-sitetopmenu can="recorder_create">
        @can('recorder_create')
            <x-input.linkbutton :label="__('Weitere Logins')" link="{{ route('loginrecorder.index', $customer) }}" />
        @endcan
    </x-sitetopmenu>

    @forelse ($recorders as $recorder)
        <x-card>
            <x-slot:head>
                <x-show.header can="recorder_update" editUrl="{{ route('recorder.edit', [$customer, $recorder]) }}">
                    {{ $recorder->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Rack' => $recorder->einbauort(),
                    'Hersteller' => $recorder->manufacturer,
                    'Model' => $recorder->model,
                    'Seriennummer' => $recorder->serialNumber,
                ]" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP' => $recorder->ip,
                    'Port' => $recorder->port,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $recorder->username,
                    'Passwort' => $recorder->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $recorders->links() }}
    </div>

</x-app-layout>
