@props(['device'])

{{-- Kompakte Fassung von x-credentialscard fuer die Listen im Tabellenlayout
     (Maschinen). Gleiche Angaben, nur untereinander statt in einer Tabelle. --}}

@can('logingeneral_viewAny')
    @php ($eintraege = $device->zugangsdaten())

    @forelse ($eintraege as $eintrag)
        <div class="py-0.5">
            <span class="text-gray-500 dark:text-gray-400">{{ $eintrag->note ?: $eintrag->login->name }}</span>
            @if ($eintrag->login->username)
                <span class="font-mono text-gray-900 dark:text-gray-100"> · {{ $eintrag->login->username }}</span>
            @endif
            @if ($eintrag->login->password)
                <x-password :value="$eintrag->login->password" width="w-24" />
            @endif
        </div>
    @empty
        <span class="text-gray-400 dark:text-gray-500">—</span>
    @endforelse
@endcan
