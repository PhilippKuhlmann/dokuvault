{{-- Ein Eintrag in der Liste. Als eigenes Teilstueck, damit die
     generische Liste (App\Livewire\ObjektListe) es einbinden kann -
     die Karte bleibt beim Typ, weil gerade ihre Unterschiede die
     Information tragen. --}}
    <x-card>
        <x-slot:head>
            <x-show.header can="certificate_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'certificate', id: {{ $eintrag->id }} })">
                {{ $eintrag->name }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard :title="__('Allgemein')" :array="[
                'Domain / CN' => $eintrag->common_name,
                'Aussteller' => $eintrag->issuer,
                'Typ' => $eintrag->type,
            ]" />
            <x-minitablecard :title="__('Gültigkeit')" :array="[
                'Ausgestellt am' => $eintrag->issued_date ? \Carbon\Carbon::parse($eintrag->issued_date)->format('d.m.Y') : null,
                'Ablaufdatum' => $eintrag->expiry_date ? \Carbon\Carbon::parse($eintrag->expiry_date)->format('d.m.Y') : null,
            ]" />
            @if ($eintrag->notes)
                <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    
