<x-app-layout :$customer>

    <x-sitetopmenu can="phone_create" />

    @forelse ($phones as $phone)
        <x-card>
            <x-slot:head>
                <x-show.header can="phone_update" editUrl="{{ route('phone.edit', [$customer, $phone]) }}">
                    Nebenstelle: {{ $phone->extension }}
                </x-show.header>
            </x-slot>

            <x-slot:body>

                <x-minitablecard :title="__('Allgemein')" :array="[
                    'Hersteller' => $phone->manufacturer,
                    'Modell' => $phone->model,
                    'Seriennummer' => $phone->serialNumber,
                ]" />

                <x-credentialscard :device="$phone" />

                <x-minitablecard :title="__('Netzwerk')" :array="[
                    'IP-Adresse' => $phone->ip,
                    'Port' => $phone->port,
                    'MAC-Adresse' => $phone->mac,
                ]" />

                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $phone->username,
                    'Passwort' => $phone->password,
                ]" />

            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse

    <div class="px-3 pb-3">
        {{ $phones->links() }}
    </div>

</x-app-layout>
