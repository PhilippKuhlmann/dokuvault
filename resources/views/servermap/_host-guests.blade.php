{{-- The guests below a host: VMs and containers each on a branch of its
     own, so the tree shows at a glance what kind runs there and how many.
     A host with one kind only gets a single card, without a fork. --}}
@php
    $cards = array_map(fn ($group) => [
        'list' => ['count' => $group['count'], 'visible' => $list['visible'], 'groups' => [$group]],
        'title' => $group['label'],
    ], $list['groups']);
@endphp
<div class="network-map-stem"></div>
@if (count($cards) > 1)
    <div class="network-map-branches">
        @foreach ($cards as $card)
            <div class="network-map-branch">
                @include('servermap._guests', $card)
            </div>
        @endforeach
    </div>
@else
    @include('servermap._guests', $cards[0])
@endif
