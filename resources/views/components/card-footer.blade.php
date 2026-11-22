{{--
    The foot of an opened card (design 1A): when its agent last reported,
    when the entry was last touched and by whom, and where else it stands -
    the activity log, the server map, the rack. Each part only where it
    exists and the user may follow the link.
--}}
@props(['entry'])

@php
    // The customer of the page when it is the entry's (no query per card),
    // else the relation - after a Livewire update there is no route.
    $routeCustomer = request()->route('customer');
    $customer = $routeCustomer instanceof \App\Models\Customer && $routeCustomer->id === $entry->customer_id
        ? $routeCustomer
        : $entry->customer;
    // Preloaded by the list (forms.php mitladen: latestActivity.causer).
    $latest = method_exists($entry, 'latestActivity') ? $entry->latestActivity : null;
    $rack = method_exists($entry, 'rackItem') ? $entry->rackItem?->rack : null;
    $inServerMap = ($entry instanceof \App\Models\Server || $entry instanceof \App\Models\VM)
        && $customer && \Illuminate\Support\Facades\Gate::allows('server_viewAny');
@endphp

<div class="flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-dashed border-gray-200 px-5 py-2.5 text-xs text-gray-500 lg:pl-14 dark:border-gray-700 dark:text-gray-400">
    {{-- The agent block of the card no longer says it - only here, the
         exact time in the tooltip. --}}
    @if ($entry->agent_reported_at ?? null)
        <span title="{{ \App\Support\LocalTime::display($entry->agent_reported_at) }}">{{ __('Agent: :zeit', ['zeit' => $entry->agent_reported_at->diffForHumans()]) }}</span>
    @endif
    @if ($latest)
        <span>
            {{ __('Geändert am :datum', ['datum' => \App\Support\LocalTime::display($latest->created_at, 'd.m.Y H:i')]) }}@if ($latest->causer)
                {{ __('von :name', ['name' => $latest->causer->name]) }}@endif
        </span>
    @endif

    @can('admin_activity')
        <a href="{{ route('admin.activity.index', ['art' => $entry->getMorphClass(), 'suche' => $entry->name]) }}"
            class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400 dark:hover:text-cerulean-300">{{ __('Verlauf') }}</a>
    @endcan
    @if ($inServerMap)
        <a href="{{ route('servermap.index', $customer) }}"
            class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400 dark:hover:text-cerulean-300">{{ __('Im Serverplan zeigen') }}</a>
    @endif
    @if ($rack && $customer && \Illuminate\Support\Facades\Gate::allows('rack_viewAny'))
        <a href="{{ route('rack.index', [$customer, 'highlight' => $rack->id]) }}"
            class="text-cerulean-600 hover:text-cerulean-700 dark:text-cerulean-400 dark:hover:text-cerulean-300">{{ __('In :rack zeigen', ['rack' => $rack->name]) }}</a>
    @endif
</div>
