<?php

namespace App\Livewire;

use App\Support\SystemMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admin -> Statistik -> System: what the installation needs right now
 * (database, files, backups, disk, memory, queue) and how it developed -
 * from the daily snapshot (stats:snapshot).
 */
class AdminSystem extends Component
{
    public function mount(): void
    {
        Gate::authorize('admin_statistik');
    }

    public function render()
    {
        // Not only in mount(): the component keeps answering updates.
        Gate::authorize('admin_statistik');

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
                'text' => Carbon::parse($z->datum)->format('d.m.Y').' · '.SystemMetrics::lesbar((int) $z->wert),
            ])->values()->all());

        $frei = SystemMetrics::diskFreeBytes();
        $gesamt = SystemMetrics::platteGesamtBytes();

        return view('livewire.admin-system', [
            'db' => SystemMetrics::datenbankBytes(),
            'tabellen' => SystemMetrics::groessteTabellen(),
            'dateien' => SystemMetrics::fileBytes(),
            'backups' => SystemMetrics::backupBytes(),
            'frei' => $frei,
            'gesamt' => $gesamt,
            'belegtProzent' => $frei !== null && $gesamt ? (int) round(100 - $frei / $gesamt * 100) : null,
            'speicher' => SystemMetrics::memory(),
            'last' => SystemMetrics::last(),
            'warteschlange' => SystemMetrics::queue(),
            'verlauf' => $verlauf,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ])->layout('layouts.admin.app');
    }
}
