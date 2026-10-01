{{-- A SIP account in the list. The blocks follow the groups of the form. --}}
<x-card>
    <x-slot:head>
        <x-show.header can="sipaccount_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'sipaccount', id: {{ $eintrag->id }} })">
            {{ $eintrag->provider }}

            @if ($eintrag->account_type)
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ __($eintrag->accountTypeLabel()) }}</span>
            @endif

            {{-- The question one has on the phone with the provider: which
                 numbers. Single numbers show the first plus a counter, like the
                 IPs of a server - the header must not grow with the list. --}}
            <x-slot:kernwerte>
                @if ($eintrag->isTrunk() && $eintrag->numbersSummary())
                    <x-kernwert :label="__('Nr.')">{{ $eintrag->numbersSummary() }}</x-kernwert>
                @elseif (! $eintrag->isTrunk() && $eintrag->numberList()->isNotEmpty())
                    <x-kernwert :label="__('Nr.')" :zaehler="$eintrag->numberList()->count() - 1">{{ $eintrag->numberList()->first() }}</x-kernwert>
                @endif

                @if ($eintrag->channels)
                    <x-kernwert :label="__('Kanäle')">{{ $eintrag->channels }}</x-kernwert>
                @endif
            </x-slot>
        </x-show.header>
    </x-slot>

    <x-slot:body>

        <x-minitablecard :title="__('Anschluss')" :array="[
            'Produkt' => $eintrag->product,
            'Standort' => $eintrag->site?->name,
            'Vertragsnummer' => $eintrag->contract_number,
            'Kundennummer' => $eintrag->provider_customer_number,
            'Hotline' => $eintrag->hotline,
            'Sprachkanäle' => $eintrag->channels,
        ]" />

        <x-minitablecard :title="__('Rufnummern')" :array="[
            'Stammnummer' => $eintrag->main_number,
            'Durchwahlbereich' => $eintrag->number_range,
        ]" />

        @if (! $eintrag->isTrunk() && filled($eintrag->numbers))
            <x-minitextcard :title="__('Einzelnummern')"><span class="whitespace-pre-line">{{ $eintrag->numbers }}</span></x-minitextcard>
        @endif

        <x-minitablecard :title="__('Technik')" :array="[
            'SIP-Registrar' => $eintrag->registrar,
            'TK-Anlage' => $eintrag->phoneSystemLabel(),
            'Internetanschluss' => $eintrag->internetConnectionLabel(),
        ]" />

        <x-credentialscard :device="$eintrag" />

        @if ($eintrag->notes)
            <x-minitextcard :title="__('Notizen')"><span class="whitespace-pre-line">{{ $eintrag->notes }}</span></x-minitextcard>
        @endif

    </x-slot>
</x-card>
