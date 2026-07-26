<x-app-layout :$customer>
    <x-sitetopmenu can="backup_create" />
    @forelse ($backups as $backup)
    <x-card>
        <x-slot:head>
            <x-show.header can="backup_update" editUrl="{{ route('backup.edit', [$customer, $backup]) }}">
                {{ $backup->name }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard title="Konfiguration" :array="[
                'Software' => $backup->software,
                'Quelle' => $backup->source,
                'Ziel' => $backup->destination,
            ]" />
            <x-minitablecard title="Zeitplan" :array="[
                'Zeitplan' => $backup->schedule,
                'Aufbewahrung' => $backup->retention,
                'Letzter Erfolg' => $backup->last_success ? \Carbon\Carbon::parse($backup->last_success)->format('d.m.Y') : null,
            ]" />
            <x-minitablecard title="Login" :array="[
                'Passwort' => $backup->password,
            ]" />
            @if ($backup->notes)
                <x-minitextcard title="Notizen">{{ $backup->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">{{ $backups->links() }}</div>
</x-app-layout>
