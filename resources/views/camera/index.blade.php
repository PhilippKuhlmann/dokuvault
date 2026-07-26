<x-app-layout :$customer>
    <x-sitetopmenu can="camera_create" />

    @forelse ($cameras as $camera)
        <x-card>
            <x-slot:head>
                <x-show.header can="camera_update" editUrl="{{ route('camera.edit', [$customer, $camera]) }}">
                    {{ $camera->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard title="Allgemein" :array="[
                    'Hersteller' => $camera->manufacturer,
                    'Model' => $camera->model,
                    'Seriennummer' => $camera->serialNumber,
                ]" />

                <x-minitablecard title="Netzwerk" :array="[
                    'IP' => $camera->ip,
                    'Port' => $camera->port,
                ]" />

                <x-minitablecard title="Login" :array="[
                    'Benutzer' => $camera->username,
                    'Passwort' => $camera->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $cameras->links() }}
    </div>

</x-app-layout>
