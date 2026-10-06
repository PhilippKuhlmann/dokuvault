<?php

namespace App\Livewire;

use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin -> Statistik -> Datenwachstum: how many servers, VMs, AD users ...
 * the documentation holds, and how that grew - in total and per customer.
 * From the daily snapshot (statistik:schnappschuss).
 */
class AdminDatenwachstum extends Component
{
    public const ZEITRAEUME = ['30' => 'Letzte 30 Tage', '90' => 'Letzte 90 Tage', '365' => 'Letztes Jahr'];

    #[Url(except: '90')]
    public string $zeitraum = '90';

    #[Url(except: 'server')]
    public string $kennzahl = 'server';

    public function mount(): void
    {
        Gate::authorize('admin_statistik');
        if (! array_key_exists($this->zeitraum, self::ZEITRAEUME)) {
            $this->zeitraum = '90';
        }
    }

    public function render()
    {
        $arten = collect(config('custom.trashables'))->mapWithKeys(fn ($e, $slug) => [$slug => $e[1]]);
        if (! $arten->has($this->kennzahl)) {
            $this->kennzahl = 'server';
        }
        $von = now()->subDays((int) $this->zeitraum)->toDateString();

        $werte = DB::table('statistik_werte')
            ->whereIn('kennzahl', $arten->keys())
            ->where('datum', '>=', $von)
            ->orderBy('datum')
            ->get();

        $erster = $werte->min('datum');
        $letzter = $werte->max('datum');

        // Per type: total at the start and now.
        $uebersicht = $arten->map(function ($name, $slug) use ($werte, $erster, $letzter) {
            $gesamt = $werte->where('kennzahl', $slug)->where('customer_id', 0);

            return [
                'slug' => $slug,
                'name' => $name,
                'anfang' => (int) ($gesamt->firstWhere('datum', $erster)->wert ?? 0),
                'jetzt' => (int) ($gesamt->firstWhere('datum', $letzter)->wert ?? 0),
            ];
        })->filter(fn ($z) => $z['jetzt'] > 0 || $z['anfang'] > 0)
            ->sortByDesc('jetzt')->values()->all();

        $verlauf = $werte->where('kennzahl', $this->kennzahl)->where('customer_id', 0)
            ->map(fn ($z) => [
                'beschriftung' => Carbon::parse($z->datum)->format('d.m.'),
                'titel' => Carbon::parse($z->datum)->format('d.m.Y'),
                'wert' => (int) $z->wert,
            ])->values()->all();

        $kunden = Customer::pluck('name', 'id');
        $jeKunde = $werte->where('kennzahl', $this->kennzahl)->where('customer_id', '>', 0)->groupBy('customer_id')
            ->map(fn ($zeilen, $kunde) => [
                'kunde' => $kunden[$kunde] ?? '#'.$kunde,
                'anfang' => (int) ($zeilen->firstWhere('datum', $erster)->wert ?? 0),
                'jetzt' => (int) ($zeilen->firstWhere('datum', $letzter)->wert ?? 0),
            ])->sortByDesc('jetzt')->values()->all();

        return view('livewire.admin-datenwachstum', [
            'zeitraeume' => self::ZEITRAEUME,
            'arten' => $arten,
            'uebersicht' => $uebersicht,
            'verlauf' => $verlauf,
            'jeKunde' => $jeKunde,
            'erster' => $erster ? Carbon::parse($erster) : null,
        ])->layout('layouts.admin.app');
    }
}
