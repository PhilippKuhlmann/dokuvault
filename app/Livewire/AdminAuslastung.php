<?php

namespace App\Livewire;

use App\Models\AgentInstallation;
use App\Support\Zeit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Load of the API (agents and API tokens), from api_request_stats.
 *
 * The question behind it: when does the load come, and does the server
 * keep up - more requests than before, or the same requests getting
 * slower, both say "time for more resources".
 */
class AdminAuslastung extends Component
{
    public const ZEITRAEUME = ['24h' => 'Letzte 24 Stunden', '7d' => 'Letzte 7 Tage', '30d' => 'Letzte 30 Tage'];

    #[Url(except: '7d')]
    public string $zeitraum = '7d';

    public function mount(): void
    {
        Gate::authorize('admin_statistik');

        if (! array_key_exists($this->zeitraum, self::ZEITRAEUME)) {
            $this->zeitraum = '7d';
        }
    }

    public function render()
    {
        // Hours of the day in the display zone: "most load at 8" means 8
        // o'clock local time, not UTC. Stored is UTC (app.timezone).
        $zone = Zeit::zone();
        $bis = now($zone)->startOfHour();
        $von = match ($this->zeitraum) {
            '24h' => $bis->copy()->subHours(23),
            '30d' => $bis->copy()->subDays(30)->startOfDay(),
            default => $bis->copy()->subDays(7)->startOfDay(),
        };

        $zeilen = DB::table('api_request_stats')->where('stunde', '>=', $von->copy()->utc())->get()
            ->map(function ($z) use ($zone) {
                $z->stunde = Carbon::parse($z->stunde, 'UTC')->setTimezone($zone);

                return $z;
            });

        return view('livewire.admin-auslastung', [
            'zeitraeume' => self::ZEITRAEUME,
            'summe' => $this->summe($zeilen),
            'verlauf' => $this->verlauf($zeilen, $von, $bis),
            'tagesprofil' => $this->tagesprofil($zeilen),
            'endpunkte' => $this->endpunkte($zeilen),
            'agenten' => AgentInstallation::count(),
        ])->layout('layouts.admin.app');
    }

    /** Totals of the period. */
    protected function summe(Collection $zeilen): array
    {
        $anzahl = $zeilen->sum('anzahl');
        $jeStunde = $zeilen->groupBy(fn ($z) => $z->stunde->format('Y-m-d H'))->map->sum('anzahl');

        return [
            'anzahl' => $anzahl,
            'agent' => $zeilen->where('art', 'agent')->sum('anzahl'),
            'api' => $zeilen->where('art', 'api')->sum('anzahl'),
            'fehler' => $zeilen->sum('fehler'),
            'dauer_schnitt' => $anzahl ? (int) round($zeilen->sum('dauer_ms_summe') / $anzahl) : 0,
            'dauer_max' => (int) $zeilen->max('dauer_ms_max'),
            'spitze' => (int) $jeStunde->max(),
            'spitze_wann' => $jeStunde->isNotEmpty() ? Carbon::createFromFormat('Y-m-d H', $jeStunde->sort()->keys()->last(), Zeit::zone()) : null,
        ];
    }

    /**
     * Bars over time: per hour for 24 hours, per day for longer periods -
     * 720 bars of a month would be a grey wall.
     */
    protected function verlauf(Collection $zeilen, Carbon $von, Carbon $bis): array
    {
        $proStunde = $this->zeitraum === '24h';
        $format = $proStunde ? 'Y-m-d H' : 'Y-m-d';
        $gruppen = $zeilen->groupBy(fn ($z) => $z->stunde->format($format));

        $balken = [];
        for ($t = $von->copy(); $t <= $bis; $proStunde ? $t->addHour() : $t->addDay()) {
            $g = $gruppen[$t->format($format)] ?? collect();
            $anzahl = $g->sum('anzahl');
            $balken[] = [
                'beschriftung' => $proStunde ? $t->format('H') : $t->format('d.m.'),
                'titel' => $proStunde ? $t->format('d.m. H:00').' Uhr' : $t->format('d.m.Y'),
                'anzahl' => $anzahl,
                'agent' => $g->where('art', 'agent')->sum('anzahl'),
                'dauer' => $anzahl ? (int) round($g->sum('dauer_ms_summe') / $anzahl) : 0,
            ];
        }

        return $balken;
    }

    /** Average requests per hour of day - when in the day the load comes. */
    protected function tagesprofil(Collection $zeilen): array
    {
        $tage = max(1, $zeilen->map(fn ($z) => $z->stunde->format('Y-m-d'))->unique()->count());
        $jeStunde = $zeilen->groupBy(fn ($z) => (int) $z->stunde->format('G'));

        return collect(range(0, 23))->map(fn ($h) => [
            'stunde' => $h,
            'schnitt' => (int) round(($jeStunde[$h] ?? collect())->sum('anzahl') / $tage),
        ])->all();
    }

    /** Per endpoint, busiest first. */
    protected function endpunkte(Collection $zeilen): array
    {
        return $zeilen->groupBy(fn ($z) => $z->art.'|'.$z->pfad)
            ->map(function ($g) {
                $anzahl = $g->sum('anzahl');

                return [
                    'art' => $g->first()->art,
                    'pfad' => $g->first()->pfad,
                    'anzahl' => $anzahl,
                    'fehler' => $g->sum('fehler'),
                    'dauer_schnitt' => $anzahl ? (int) round($g->sum('dauer_ms_summe') / $anzahl) : 0,
                    'dauer_max' => (int) $g->max('dauer_ms_max'),
                ];
            })
            ->sortByDesc('anzahl')
            ->values()
            ->all();
    }
}
