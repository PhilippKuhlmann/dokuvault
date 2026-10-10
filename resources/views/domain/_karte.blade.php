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
            'Laut Registry' => $eintrag->registry_registrar !== $eintrag->registrar ? $eintrag->registry_registrar : null,
            'Ablaufdatum' => $eintrag->expiry_date ? \Carbon\Carbon::parse($eintrag->expiry_date)->format('d.m.Y') : null,
        ]" />
        <x-minitablecard :title="__('Nameserver')" :array="[
            'NS 1' => $eintrag->nameserver1,
            'NS 2' => $eintrag->nameserver2,
        ]" />
        <x-minitablecard :title="__('E-Mail')" :array="[
            'MX' => $eintrag->mx,
            'SPF' => $eintrag->spf,
            'DMARC' => $eintrag->dmarc,
        ]" />
        {{-- One line per selector with a key: what is set up and how strong.
             A key only says the DNS side is done - whether the server signs
             with it shows in the header of a received mail. --}}
        @if ($eintrag->dkim)
            <div class="w-full mb-5 break-inside-avoid">
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('DKIM') }}</div>
                <table class="w-full text-sm dark:text-gray-100">
                    @foreach ($eintrag->dkim as $schluessel)
                        @php
                            $schwach = ! $schluessel['revoked'] && $schluessel['type'] === 'rsa' && $schluessel['bits'] && $schluessel['bits'] < 2048;
                            $art = $schluessel['type'] === 'ed25519' ? 'Ed25519' : strtoupper($schluessel['type']);
                            $beschreibung = $schluessel['revoked']
                                ? __('zurückgezogen (leerer Schlüssel)')
                                : __('eingerichtet').' · '.$art.($schluessel['bits'] ? ' '.__(':bits Bit', ['bits' => $schluessel['bits']]) : '');
                        @endphp
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ __('Selektor :name', ['name' => $schluessel['selector']]) }}</td>
                            <td class="py-1 {{ $schluessel['revoked'] || $schwach ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                {{ $beschreibung }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
        {{-- Reverse DNS of every mail server address: green when the PTR
             name resolves back to the same address, as receivers check. --}}
        @if ($eintrag->ptr)
            <div class="w-full mb-5 break-inside-avoid">
                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Reverse DNS (PTR)') }}</div>
                <table class="w-full text-sm dark:text-gray-100">
                    @foreach ($eintrag->ptr as $zeile)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="py-1 pr-6 align-top text-gray-500 dark:text-gray-400">{{ $zeile['ip'] }}</td>
                            <td class="py-1 break-all {{ $zeile['ok'] ? '' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $zeile['ptr'] ?? __('kein PTR') }}
                                <span class="text-xs">{{ $zeile['ok'] ? '✓' : '✗' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
        {{-- Only after a check that reached the zone (nameservers found): a
             missing record is then real, not a lookup that failed. --}}
        @php
            $hinweise = [];
            if ($eintrag->checked_at && $eintrag->nameserver1 && ! $eintrag->check_error && $eintrag->mx) {
                if (! $eintrag->spf) {
                    $hinweise[] = __('Kein SPF-Eintrag – Mails dieser Domain landen leichter im Spam.');
                }
                if (! $eintrag->dmarc) {
                    $hinweise[] = __('Kein DMARC-Eintrag.');
                }
                $aktiv = collect($eintrag->dkim ?? [])->where('revoked', false);
                if ($aktiv->isEmpty()) {
                    $hinweise[] = __('Kein DKIM-Schlüssel unter den üblichen Selektoren gefunden. Ist ein eigener Selektor im Einsatz, im Formular eintragen.');
                }
                foreach ($aktiv->where('type', 'rsa')->filter(fn ($k) => $k['bits'] && $k['bits'] < 2048) as $k) {
                    $hinweise[] = __('DKIM-Schlüssel unter Selektor :name hat nur :bits Bit – 2048 Bit sind heute Standard.', ['name' => $k['selector'], 'bits' => $k['bits']]);
                }
                if (collect($eintrag->ptr ?? [])->contains('ok', false)) {
                    $hinweise[] = __('Reverse DNS eines Mailservers fehlt oder passt nicht zur Adresse.');
                }
            }
        @endphp
        <x-auto-check :eintrag="$eintrag" can="domain_update" :hinweise="$hinweise" />
        @if ($eintrag->notes)
            <x-minitextcard :title="__('Notizen')">{{ $eintrag->notes }}</x-minitextcard>
        @endif
    </x-slot>
</x-card>
