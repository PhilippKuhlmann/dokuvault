<x-app-layout :$customer>
    <x-sitetopmenu can="domain_create" />
    @forelse ($domains as $domain)
    <x-card>
        <x-slot:head>
            <x-show.header can="domain_update" editUrl="{{ route('domain.edit', [$customer, $domain]) }}">
                {{ $domain->name }}
            </x-show.header>
        </x-slot>
        <x-slot:body>
            <x-minitablecard :title="__('Allgemein')" :array="[
                'Registrar' => $domain->registrar,
                'Ablaufdatum' => $domain->expiry_date ? \Carbon\Carbon::parse($domain->expiry_date)->format('d.m.Y') : null,
            ]" />
            <x-minitablecard :title="__('Nameserver')" :array="[
                'NS 1' => $domain->nameserver1,
                'NS 2' => $domain->nameserver2,
            ]" />
            @if ($domain->notes)
                <x-minitextcard :title="__('Notizen')">{{ $domain->notes }}</x-minitextcard>
            @endif
        </x-slot>
    </x-card>
    @empty
    <x-emptystate />
@endforelse
    <div class="px-3 pb-3">{{ $domains->links() }}</div>
</x-app-layout>
