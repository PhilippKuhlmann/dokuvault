{{-- Ein Eintrag in der Liste. Als eigenes Teilstueck, damit die
     generische Liste (App\Livewire\ObjektListe) es einbinden kann -
     die Karte bleibt beim Typ, weil gerade ihre Unterschiede die
     Information tragen. --}}
    <x-card>
        <x-slot:head>
            <x-show.header can="backup_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'backup', id: {{ $eintrag->id }} })">
                {{ $eintrag->name }}
                {{-- Status of the last run, where an agent reported one. --}}
                @if ($eintrag->last_status)
                    <span @class([
                        'ml-2 rounded px-1.5 py-0.5 align-middle text-[10px] font-semibold uppercase tracking-wide',
                        'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $eintrag->last_status === 'ok',
                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $eintrag->last_status === 'warning',
                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $eintrag->last_status === 'failed',
                    ])>{{ __(\App\Models\Backup::STATUS[$eintrag->last_status] ?? $eintrag->last_status) }}</span>
                @endif
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
                'Letzter Lauf' => $eintrag->last_run_at
                    ? $eintrag->last_run_at->format('d.m.Y H:i').($eintrag->last_status ? ' · '.__(\App\Models\Backup::STATUS[$eintrag->last_status] ?? $eintrag->last_status) : '')
                    : null,
            ]" />
            {{-- Reported runs, as on the admin overview: as many as set
                 there. Only for backups an agent reports. --}}
            @if ($eintrag->agent_identifier)
                <x-minitextcard :title="__('Letzte Läufe')">
                    <x-backup-verlauf :runs="$eintrag->recentRuns" />
                </x-minitextcard>
            @endif
            <x-minitablecard :title="__('Login')" :array="[
                'Passwort' => $eintrag->password,
            ]" />
            @if ($eintrag->notes)
                <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    
