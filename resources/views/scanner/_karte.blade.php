{{-- Ein Eintrag in der Liste. Die Karte bleibt beim Typ. --}}
        @php
            $adressen = $eintrag->relationLoaded('ipAddresses') ? $eintrag->ipAddresses : $eintrag->ipAddresses()->get();
            $primaer = $adressen->first()?->anzeige();
            $anzahlIps = $adressen->count();
        @endphp
    <x-card>
        <x-slot:head>
            <x-show.header can="scanner_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'scanner', id: {{ $eintrag->id }} })">
                {{ $eintrag->name }}

                    {{-- Was man fast immer sucht, neben dem Namen. --}}
                    <x-slot:kernwerte>
                        @if ($primaer)
                            <x-kernwert :label="__('IP')" :zaehler="$anzahlIps - 1">
                                <x-ip-anzeige :adresse="$adressen->first()" />
                            </x-kernwert>
                        @endif
                    </x-slot>
                </x-show.header>
        </x-slot>

        <x-slot:body>

            <x-ipcard :device="$eintrag" />

            <x-minitablecard :title="__('Allgemein')" :array="[
                'Hersteller' => $eintrag->manufacturer,
                'Modell' => $eintrag->model,
                'Seriennummer' => $eintrag->serialNumber,
            ]" />

            <x-credentialscard :device="$eintrag" />

            {{-- Scan-Ziele: Bezeichnung und Ziel je Zeile, die Art klein
                 dahinter. Vorgeladen ueber config/forms.php (mitladen); die
                 Zugangsdaten dazu stehen an der Karte des Ziels. --}}
            @php ($ziele = $eintrag->relationLoaded('scanTargets') ? $eintrag->scanTargets : $eintrag->scanTargets()->get())
            @if ($ziele->isNotEmpty())
                <div class="w-full mb-5 break-inside-avoid">
                    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Scan-Ziele') }}
                    </div>
                    <table class="w-full text-sm dark:text-gray-100">
                        @foreach ($ziele as $ziel)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                                <td class="py-1 pr-3 align-top text-gray-500 dark:text-gray-400">{{ $ziel->name ?: $ziel->artName() }}</td>
                                <td class="py-1 align-top font-mono text-xs break-all">
                                    {{ $ziel->target }}
                                    @if ($ziel->name)
                                        <span class="ml-1 font-sans text-[10px] text-gray-400 dark:text-gray-500">{{ $ziel->artName() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            <x-beschaffungcard :device="$eintrag" />

        </x-slot>
    </x-card>
