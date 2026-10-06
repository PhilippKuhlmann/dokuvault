<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin -> Statistik -> Nutzung: sign-ins and changes per day, who works
 * most, which areas change most - from the activity log. Only as far back
 * as the log keeps entries (Protokoll-Historie).
 */
class AdminNutzung extends Component
{
    public const ZEITRAEUME = ['7' => 'Letzte 7 Tage', '30' => 'Letzte 30 Tage', '90' => 'Letzte 90 Tage'];

    public const AENDERUNGEN = ['created', 'updated', 'deleted', 'restored'];

    #[Url(except: '30')]
    public string $zeitraum = '30';

    public function mount(): void
    {
        Gate::authorize('admin_statistik');
        if (! array_key_exists($this->zeitraum, self::ZEITRAEUME)) {
            $this->zeitraum = '30';
        }
    }

    public function render()
    {
        $tage = (int) $this->zeitraum;
        $von = now()->subDays($tage - 1)->startOfDay();

        $eintraege = Activity::query()
            ->where('created_at', '>=', $von)
            ->whereIn('event', [...self::AENDERUNGEN, 'anmeldung', 'anmeldung_gescheitert', 'anmeldung_gesperrt'])
            ->get(['id', 'event', 'causer_type', 'causer_id', 'subject_type', 'created_at']);

        $anmeldungen = $eintraege->where('event', 'anmeldung');
        $aenderungen = $eintraege->whereIn('event', self::AENDERUNGEN);

        $proTag = function ($menge) use ($von, $tage) {
            $je = $menge->groupBy(fn ($e) => $e->created_at->format('Y-m-d'))->map->count();

            return collect(range(0, $tage - 1))->map(function ($i) use ($von, $je) {
                $tag = $von->copy()->addDays($i);

                return ['beschriftung' => $tag->format('d.m.'), 'titel' => $tag->format('d.m.Y'), 'wert' => (int) ($je[$tag->format('Y-m-d')] ?? 0)];
            })->all();
        };

        $namen = User::whereIn('id', $eintraege->where('causer_type', User::class)->pluck('causer_id')->unique())->pluck('name', 'id');
        $jeBenutzer = $eintraege->where('causer_type', User::class)->groupBy('causer_id')
            ->map(fn ($e, $id) => [
                'name' => $namen[$id] ?? '#'.$id,
                'anmeldungen' => $e->where('event', 'anmeldung')->count(),
                'aenderungen' => $e->whereIn('event', self::AENDERUNGEN)->count(),
                'zuletzt' => $e->max('created_at'),
            ])->sortByDesc('aenderungen')->values()->all();

        $bereiche = collect(config('custom.trashables'))->mapWithKeys(fn ($e) => [$e[0] => $e[1]]);
        $jeBereich = $aenderungen->groupBy('subject_type')
            ->map(fn ($e, $typ) => ['name' => $bereiche[$typ] ?? class_basename($typ), 'anzahl' => $e->count()])
            ->sortByDesc('anzahl')->take(12)->values()->all();

        return view('livewire.admin-nutzung', [
            'zeitraeume' => self::ZEITRAEUME,
            'summe' => [
                'anmeldungen' => $anmeldungen->count(),
                'gescheitert' => $eintraege->whereIn('event', ['anmeldung_gescheitert', 'anmeldung_gesperrt'])->count(),
                'aenderungen' => $aenderungen->count(),
                'benutzer' => $anmeldungen->pluck('causer_id')->unique()->count(),
            ],
            'anmeldungenProTag' => $proTag($anmeldungen),
            'aenderungenProTag' => $proTag($aenderungen),
            'jeBenutzer' => $jeBenutzer,
            'jeBereich' => $jeBereich,
        ])->layout('layouts.admin.app');
    }
}
