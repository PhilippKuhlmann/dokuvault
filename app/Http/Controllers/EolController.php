<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Setting;
use App\Models\VM;

/**
 * Every device whose operating system runs out of support - grouped by customer.
 *
 * The admin dashboard shows only which systems are affected. For talking to
 * customers the other direction is needed: which customer has how many
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
        // The same threshold as on the badge - and now the same
        // Quelle. Zwei Zahlen mit demselben Kommentar laufen sonst auseinander.
        $systeme = OperatingSystem::whereNotNull('eol_date')
            ->whereDate('eol_date', '<=', now()->addDays(Setting::eolDeadline()))
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

        // Grouped by customer; whoever has the most expired systems stands on top.
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
