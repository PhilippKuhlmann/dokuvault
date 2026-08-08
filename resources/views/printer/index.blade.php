<x-app-layout :$customer>

    <x-sitetopmenu can="printer_create" />

    @forelse ($printers as $printer)
    <x-card>
        <x-slot:head>
            <x-show.header can="printer_update" editUrl="{{ route('printer.edit', [$customer, $printer]) }}">
                {{ $printer->name }}
            </x-show.header>
        </x-slot>

        <x-slot:body>

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $printer->manufacturer,
                'Modell' => $printer->model,
                'Seriennummer' => $printer->serialNumber,
            ]" />

            <x-credentialscard :device="$printer" />

            <x-minitablecard :title="__('Netzwerk')" :array="[
                'IP-Adresse' => $printer->ip,
                'Port' => $printer->port,
            ]" />

            <x-minitablecard :title="__('Login')" :array="[
                'Benutzer' => $printer->username,
                'Passwort' => $printer->password,
            ]" />

        </x-slot>
    </x-card>
@empty
    <x-emptystate />
@endforelse


    <div class="px-3 pb-3">
        {{ $printers->links() }}
    </div>

</x-app-layout>
