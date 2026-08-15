@props(['os'])

{{-- Abzeichen am Betriebssystem. Rot heisst: laeuft, bekommt aber keine
     Sicherheitsupdates mehr. Bernstein heisst: das steht im naechsten halben
     Jahr an. Ohne gepflegtes Datum erscheint nichts - eine graue Kachel
     "unbekannt" an jedem Geraet waere nur Rauschen. --}}

@if ($os?->istEol())
    <span title="{{ __('Support endete am') }} {{ $os->eol_date->format('d.m.Y') }}"
        class="inline-flex items-center gap-1 rounded bg-rose-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-rose-800 dark:bg-rose-900 dark:text-rose-100">
        {{ __('EOL') }}
        <span class="font-mono font-normal normal-case tracking-normal">{{ $os->eol_date->format('m/Y') }}</span>
    </span>
@elseif ($os?->laeuftAus())
    <span title="{{ __('Support endet am') }} {{ $os->eol_date->format('d.m.Y') }}"
        class="inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-900 dark:text-amber-100">
        {{ __('EOL bald') }}
        <span class="font-mono font-normal normal-case tracking-normal">{{ $os->eol_date->format('m/Y') }}</span>
    </span>
@endif
