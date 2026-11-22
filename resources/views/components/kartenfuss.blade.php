{{--
    The foot of an opened card (design 1A): when its agent last reported,
    when the entry was last touched and by whom, and where else it stands -
    the activity log, the server map, the rack. Each part only where it
    exists and the user may follow the link.
--}}
@props(['eintrag'])

@php
    // The customer of the page when it is the entry's (no query per card),
    // else the relation - after a Livewire update there is no route.
    $routenKunde = request()->route('customer');
    $kunde = $routenKunde instanceof \App\Models\Customer && $routenKunde->id === $eintrag->customer_id
        ? $routenKunde
        : $eintrag->customer;
    // Preloaded by the list (forms.php mitladen: latestActivity.causer).
    $letzte = method_exists($eintrag, 'latestActivity') ? $eintrag->latestActivity : null;
    $rack = method_exists($eintrag, 'rackItem') ? $eintrag->rackItem?->rack : null;
    $imPlan = ($eintrag instanceof \App\Models\Server || $eintrag instanceof \App\Models\VM)
        && $kunde && \Illuminate\Support\Facades\Gate::allows('server_viewAny');
@endphp

<div class="flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-dashed border-gray-200 px-5 py-2.5 text-xs text-gray-500 lg:pl-14 dark:border-gray-700 dark:text-gray-400">
    {{-- The agent block of the card no longer says it - only here, the
         exact time in the tooltip. --}}
    @if ($eintrag->agent_reported_at ?? null)
        <span title="{{ \App\Support\LocalTime::display($eintrag->agent_reported_at) }}">{{ __('Agent: :zeit', ['zeit' => $eintrag->agent_reported_at->diffForHumans()]) }}</span>
    @endif
    @if ($letzte)
        <span>
            {{ __('Geändert am :datum', ['datum' => \App\Support\LocalTime::display($letzte->created_at, 'd.m.Y H:i')]) }}@if ($letzte->causer)
                {{ __('von :name', ['name' => $letzte->causer->name]) }}@endif
        </span>
    @endif

    @can('admin_activity')
        <a href="{{ route('admin.activity.index', ['art' => $eintrag->getMorphClass(), 'suche' => $eintrag->name]) }}"
            class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400 dark:hover:text-cerulean-300">{{ __('Verlauf') }}</a>
    @endcan
    @if ($imPlan)
        <a href="{{ route('servermap.index', $kunde) }}"
            class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400 dark:hover:text-cerulean-300">{{ __('Im Serverplan zeigen') }}</a>
    @endif
    @if ($rack && $kunde && \Illuminate\Support\Facades\Gate::allows('rack_viewAny'))
        <a href="{{ route('rack.index', [$kunde, 'highlight' => $rack->id]) }}"
            class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400 dark:hover:text-cerulean-300">{{ __('In :rack zeigen', ['rack' => $rack->name]) }}</a>
    @endif
</div>
