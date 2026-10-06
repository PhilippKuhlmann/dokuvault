{{-- Period buttons of the statistics pages (wire:click sets $zeitraum). --}}
@props(['zeitraeume', 'aktiv'])

<div class="flex gap-1">
    @foreach ($zeitraeume as $wert => $beschriftung)
        <button type="button" wire:click="$set('zeitraum', '{{ $wert }}')" @class([
            'rounded-lg px-3 py-1.5 text-sm transition-colors',
            'bg-cerulean-600 text-white' => (string) $aktiv === (string) $wert,
            'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' => (string) $aktiv !== (string) $wert,
        ])>{{ __($beschriftung) }}</button>
    @endforeach
</div>
