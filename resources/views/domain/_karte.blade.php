{{-- Eine Domain in der Liste. Als eigenes Teilstueck, damit die generische
     Liste (App\Livewire\ObjektListe) es einbinden kann - die Karte bleibt beim
     Typ, weil gerade ihre Unterschiede die Information tragen. --}}
<x-card>
    <x-slot:head>
        {{-- Bearbeiten oeffnet das Modal, statt auf eine eigene Seite zu fuehren. --}}
        <x-show.header can="domain_update"
            editAction="$dispatch('objekt-bearbeiten', { typ: 'domain', id: {{ $eintrag->id }} })">
            {{ $eintrag->name }}
        </x-show.header>
    </x-slot>
    <x-slot:body>
        <x-minitablecard :title="__('Allgemein')" :array="[
            'Registrar' => $eintrag->registrar,
            'Ablaufdatum' => $eintrag->expiry_date ? \Carbon\Carbon::parse($eintrag->expiry_date)->format('d.m.Y') : null,
        ]" />
        <x-minitablecard :title="__('Nameserver')" :array="[
            'NS 1' => $eintrag->nameserver1,
            'NS 2' => $eintrag->nameserver2,
        ]" />
        @if ($eintrag->notes)
            <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
        @endif
    </x-slot>
</x-card>
