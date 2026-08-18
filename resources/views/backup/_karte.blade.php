{{-- Ein Eintrag in der Liste. Als eigenes Teilstueck, damit die
     generische Liste (App\Livewire\ObjektListe) es einbinden kann -
     die Karte bleibt beim Typ, weil gerade ihre Unterschiede die
     Information tragen. --}}
    <x-card>
        <x-slot:head>
            <x-show.header can="backup_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'backup', id: {{ $eintrag->id }} })">
                {{ $eintrag->name }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard :title="__('Konfiguration')" :array="[
                'Software' => $eintrag->software,
                'Quelle' => $eintrag->source,
                'Ziel' => $eintrag->destination,
            ]" />
            <x-minitablecard :title="__('Zeitplan')" :array="[
                'Zeitplan' => $eintrag->schedule,
                'Aufbewahrung' => $eintrag->retention,
                'Letzter Erfolg' => $eintrag->last_success ? \Carbon\Carbon::parse($eintrag->last_success)->format('d.m.Y') : null,
            ]" />
            <x-minitablecard :title="__('Login')" :array="[
                'Passwort' => $eintrag->password,
            ]" />
            @if ($eintrag->notes)
                <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    
