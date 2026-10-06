<?php

namespace App\Livewire;

use App\Support\SystemWerte;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admin -> Statistik -> System: what the installation needs right now
 * (database, files, backups, disk, memory, queue) and how it developed -
 * from the daily snapshot (statistik:schnappschuss).
 */
class AdminSystem extends Component
{
    public function mount(): void
    {
        Gate::authorize('admin_statistik');
    }

    public function render()
    {
        $verlauf = DB::table('statistik_werte')
            ->where('customer_id', 0)
            ->whereIn('kennzahl', ['db_bytes', 'dateien_bytes', 'backup_bytes', 'disk_frei_bytes'])
            ->where('datum', '>=', now()->subDays(90)->toDateString())
            ->orderBy('datum')
            ->get()
            ->groupBy('kennzahl')
            ->map(fn ($zeilen) => $zeilen->map(fn ($z) => [
                'beschriftung' => Carbon::parse($z->datum)->format('d.m.'),
                'titel' => Carbon::parse($z->datum)->format('d.m.Y'),
                'wert' => (int) round($z->wert / 1048576),
                'text' => Carbon::parse($z->datum)->format('d.m.Y').' · '.SystemWerte::lesbar((int) $z->wert),
            ])->values()->all());

        $frei = SystemWerte::platteFreiBytes();
        $gesamt = SystemWerte::platteGesamtBytes();

        return view('livewire.admin-system', [
            'db' => SystemWerte::datenbankBytes(),
            'tabellen' => SystemWerte::groessteTabellen(),
            'dateien' => SystemWerte::dateienBytes(),
            'backups' => SystemWerte::backupBytes(),
            'frei' => $frei,
            'gesamt' => $gesamt,
            'belegtProzent' => $frei !== null && $gesamt ? (int) round(100 - $frei / $gesamt * 100) : null,
            'speicher' => SystemWerte::arbeitsspeicher(),
            'last' => SystemWerte::last(),
            'warteschlange' => SystemWerte::warteschlange(),
            'verlauf' => $verlauf,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ])->layout('layouts.admin.app');
    }
}
