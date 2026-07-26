<x-app-layout :$customer>
    <x-sitetopmenu can="certificate_create" />
    @forelse ($certificates as $certificate)
    <x-card>
        <x-slot:head>
            <x-show.header can="certificate_update" editUrl="{{ route('certificate.edit', [$customer, $certificate]) }}">
                {{ $certificate->name }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard title="Allgemein" :array="[
                'Domain / CN' => $certificate->common_name,
                'Aussteller' => $certificate->issuer,
                'Typ' => $certificate->type,
            ]" />
            <x-minitablecard title="Gültigkeit" :array="[
                'Ausgestellt am' => $certificate->issued_date ? \Carbon\Carbon::parse($certificate->issued_date)->format('d.m.Y') : null,
                'Ablaufdatum' => $certificate->expiry_date ? \Carbon\Carbon::parse($certificate->expiry_date)->format('d.m.Y') : null,
            ]" />
            @if ($certificate->notes)
                <x-minitextcard title="Notizen">{{ $certificate->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">{{ $certificates->links() }}</div>
</x-app-layout>
