{{-- Eine Software-Lizenz in der Liste. --}}
        <x-card>
            <x-slot:head>
                <x-show.header can="licensesoftware_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'licensesoftware', id: {{ $eintrag->id }} })">
                    {{ $eintrag->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-minitablecard :title="__('Login')" :array="[
                    'Benutzer' => $eintrag->username,
                    'Passwort' => $eintrag->password,
                ]" />

                <x-minitablecard :title="__('Laufzeit')" :array="[
                    'Start Datum' => $eintrag->start_date,
                    'End Datum' => $eintrag->end_date,
                    'Abrechnung' => $eintrag->abo,
                ]" />

                <x-minitextcard :title="__('Datei')">
                    @if ($eintrag->file_path)
                        <a href="{{ route('licensesoftware.download', [$customer, $eintrag]) }}" class="text-cerulean-600 hover:text-cerulean-700 hover:underline">{{ $eintrag->file_name }} – Download</a>
                    @endif
                </x-minitextcard>

                <x-minitextcard :title="__('Key')">
                    {{ $eintrag->key }}
                </x-minitextcard>




            </x-slot>
        </x-card>
    
