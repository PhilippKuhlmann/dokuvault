<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Carbon;
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

        // Counted in the database, not in PHP: reading every entry of the
        // period took 2.6 s with 96,000 entries. Each query below returns
        // at most a few hundred rows (days x events, users, object types).
        $anmeldung = ['anmeldung', 'anmeldung_gescheitert', 'anmeldung_gesperrt'];
        $basis = fn () => Activity::query()->where('created_at', '>=', $von);

        $jeTagUndEreignis = $basis()
            ->whereIn('event', [...self::AENDERUNGEN, ...$anmeldung])
            ->selectRaw('DATE(created_at) as tag, event, COUNT(*) as anzahl')
            ->groupBy('tag', 'event')
            ->toBase()->get();

        $proTag = function (array $ereignisse) use ($von, $tage, $jeTagUndEreignis) {
            $je = $jeTagUndEreignis->whereIn('event', $ereignisse)->groupBy('tag')->map->sum('anzahl');

            return collect(range(0, $tage - 1))->map(function ($i) use ($von, $je) {
                $tag = $von->copy()->addDays($i);

                return ['beschriftung' => $tag->format('d.m.'), 'titel' => $tag->format('d.m.Y'), 'wert' => (int) ($je[$tag->format('Y-m-d')] ?? 0)];
            })->all();
        };
        $summe = fn (array $ereignisse) => (int) $jeTagUndEreignis->whereIn('event', $ereignisse)->sum('anzahl');

        $platzhalter = implode(',', array_fill(0, count(self::AENDERUNGEN), '?'));
        $jeNutzer = $basis()
            ->where('causer_type', User::class)
            ->whereIn('event', [...self::AENDERUNGEN, 'anmeldung'])
            ->selectRaw(
                "causer_id, SUM(CASE WHEN event = 'anmeldung' THEN 1 ELSE 0 END) as anmeldungen,"
                ." SUM(CASE WHEN event IN ($platzhalter) THEN 1 ELSE 0 END) as aenderungen, MAX(created_at) as zuletzt",
                self::AENDERUNGEN
            )
            ->groupBy('causer_id')
            ->toBase()->get();

        $namen = User::whereIn('id', $jeNutzer->pluck('causer_id'))->pluck('name', 'id');
        $jeBenutzer = $jeNutzer
            ->map(fn ($z) => [
                'name' => $namen[$z->causer_id] ?? '#'.$z->causer_id,
                'anmeldungen' => (int) $z->anmeldungen,
                'aenderungen' => (int) $z->aenderungen,
                'zuletzt' => Carbon::parse($z->zuletzt),
            ])->sortByDesc('aenderungen')->values()->all();

        $bereiche = collect(config('custom.trashables'))->mapWithKeys(fn ($e) => [$e[0] => $e[1]]);
        $jeBereich = $basis()
            ->whereIn('event', self::AENDERUNGEN)
            ->selectRaw('subject_type, COUNT(*) as anzahl')
            ->groupBy('subject_type')
            ->orderByDesc('anzahl')->limit(12)
            ->toBase()->get()
            ->map(fn ($z) => ['name' => $bereiche[$z->subject_type] ?? class_basename((string) $z->subject_type), 'anzahl' => (int) $z->anzahl])
            ->all();

        return view('livewire.admin-nutzung', [
            'zeitraeume' => self::ZEITRAEUME,
            'summe' => [
                'anmeldungen' => $summe(['anmeldung']),
                'gescheitert' => $summe(['anmeldung_gescheitert', 'anmeldung_gesperrt']),
                'aenderungen' => $summe(self::AENDERUNGEN),
                'benutzer' => $basis()->where('event', 'anmeldung')->distinct()->count('causer_id'),
            ],
            'anmeldungenProTag' => $proTag(['anmeldung']),
            'aenderungenProTag' => $proTag(self::AENDERUNGEN),
            'jeBenutzer' => $jeBenutzer,
            'jeBereich' => $jeBereich,
        ])->layout('layouts.admin.app');
    }
}
