{{-- The state of DokuVault's own backup in one line (App\Support\BackupZustand):
     admin dashboard, Statistik -> System and the backup page. Fixed height;
     a long error message is cut and shown whole on hover. --}}
@php
    $zustand = \App\Support\BackupZustand::zustand();
    $farbe = [
        'ok' => 'bg-emerald-500',
        'warnung' => 'bg-amber-500',
        'fehler' => 'bg-red-500',
    ][$zustand['stufe']];
    $rahmen = [
        'ok' => 'border-gray-200 dark:border-gray-700',
        'warnung' => 'border-amber-300 dark:border-amber-700',
        'fehler' => 'border-red-300 dark:border-red-700',
    ][$zustand['stufe']];
@endphp

<div {{ $attributes->class(['flex h-14 items-center gap-3 rounded-xl border bg-white px-4 shadow-xs dark:bg-gray-800', $rahmen]) }}>
    <span class="h-3 w-3 shrink-0 rounded-full {{ $farbe }}"></span>
    <div class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2 text-sm">
            <span class="shrink-0 font-semibold text-gray-900 dark:text-gray-100">{{ __('Sicherung von DokuVault') }}</span>
            <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                {{ $zustand['letzte'] ? __('zuletzt :wann', ['wann' => \App\Support\Zeit::anzeigen($zustand['letzte'])]) : '' }}
            </span>
        </div>
        <div class="truncate text-xs text-gray-600 dark:text-gray-300" title="{{ $zustand['text'] }}">{{ $zustand['text'] }}</div>
    </div>
    @can('admin_setting')
        @unless (request()->routeIs('admin.backup.einstellungen'))
            <a href="{{ route('admin.backup.einstellungen') }}" class="shrink-0 text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">{{ __('Einstellungen') }}</a>
        @endunless
    @endcan
</div>
