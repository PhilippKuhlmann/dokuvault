<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\VM;

/**
 * Alle Geräte, deren Betriebssystem aus dem Support läuft - nach Kunde gruppiert.
 *
 * Das Admin-Dashboard zeigt nur, welche Systeme betroffen sind. Für die
 * Kundenansprache braucht es die Gegenrichtung: Welcher Kunde hat wie viele
 * Maschinen auf einem abgelaufenen System.
 */
class EolController extends Controller
{
    /** Geräteklassen mit Betriebssystem, Anzeigename => Model. */
    private const TYPEN = [
        'Server' => Server::class,
        'VM' => VM::class,
        'Computer' => Computer::class,
    ];

    public function index()
    {
        // Ein halbes Jahr Vorlauf, dieselbe Schwelle wie beim Abzeichen.
        $systeme = OperatingSystem::whereNotNull('eol_date')
            ->whereDate('eol_date', '<=', now()->addDays(180))
            ->get()
            ->keyBy('id');

        $geraete = collect();

        foreach (self::TYPEN as $bezeichnung => $klasse) {
            foreach ($klasse::whereIn('operating_system_id', $systeme->keys())->with('customer')->get() as $geraet) {
                $geraete->push([
                    'typ' => $bezeichnung,
                    'name' => $geraet->name,
                    'kunde' => $geraet->customer,
                    'os' => $systeme[$geraet->operating_system_id],
                    'route' => $geraet->customer
                        ? route(strtolower(class_basename($klasse)).'.index', $geraet->customer)
                        : null,
                ]);
            }
        }

        // Nach Kunde gruppiert; wer die meisten abgelaufenen Systeme hat, steht oben.
        $nachKunde = $geraete
            ->sortBy(fn ($g) => $g['os']->eol_date)
            ->groupBy(fn ($g) => $g['kunde']?->name ?? __('Ohne Kunde'))
            ->sortByDesc(fn ($gruppe) => $gruppe->filter(fn ($g) => $g['os']->istEol())->count());

        return view('admin.eol.index', [
            'nachKunde' => $nachKunde,
            'anzahlAbgelaufen' => $geraete->filter(fn ($g) => $g['os']->istEol())->count(),
            'anzahlBald' => $geraete->filter(fn ($g) => $g['os']->laeuftAus())->count(),
        ]);
    }
}
