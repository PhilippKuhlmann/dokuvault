{{-- Result of the automatic check (domains:check) on a domain or certificate
     card: when it ran, what went wrong, what is worth a look - and the button
     to run it now. Shown only when the entry is checked at all. --}}
@props(['eintrag', 'can', 'hinweise' => []])

@if ($eintrag->auto_check)
    <div class="w-full mb-5 break-inside-avoid text-sm">
        <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('Automatische Prüfung') }}
        </div>

        @if ($eintrag->check_error)
            <p class="text-red-600 dark:text-red-400">{{ $eintrag->check_error }}</p>
        @endif

        @foreach ($hinweise as $hinweis)
            <p class="text-amber-600 dark:text-amber-400">{{ $hinweis }}</p>
        @endforeach

        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
            <span>
                {{ $eintrag->checked_at
                    ? __('Geprüft am :datum', ['datum' => \App\Support\Zeit::anzeigen($eintrag->checked_at)])
                    : __('Noch nicht geprüft') }}
            </span>
            @can($can)
                <button type="button" wire:click="check({{ $eintrag->id }})" wire:loading.attr="disabled"
                    wire:target="check({{ $eintrag->id }})"
                    class="text-cerulean-600 hover:underline disabled:opacity-50 dark:text-cerulean-400">
                    <span wire:loading.remove wire:target="check({{ $eintrag->id }})">{{ __('Jetzt prüfen') }}</span>
                    <span wire:loading wire:target="check({{ $eintrag->id }})">{{ __('prüft …') }}</span>
                </button>
            @endcan
        </div>
    </div>
@endif
