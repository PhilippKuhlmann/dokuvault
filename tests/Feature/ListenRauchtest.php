<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Site;
use Illuminate\Support\Facades\Schema;

/**
 * Jede Liste einmal mit Inhalt aufrufen.
 *
 * Gefunden hat das die Maschinen-Liste: Sie griff in der Schleife auf ein
 * $adressen zu, das dort nie gesetzt wurde, und antwortete mit 500 - sobald
 * ueberhaupt eine Maschine angelegt war. Eine leere Liste haette den Fehler
 * nie gezeigt, deshalb legt der Test je Typ zwei Eintraege an.
 */
test('jede Liste rendert mit Inhalt', function () {
    $rechte = [];

    foreach (array_keys(config('custom.trashables')) as $key) {
        $rechte[] = $key.'_viewAny';
        $rechte[] = $key.'_update';
        $rechte[] = $key.'_create';
    }

    $this->actingAs(userWithPermissions($rechte));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    session(['site' => 'all']);

    $ohneDaten = [];

    foreach (config('custom.trashables') as $key => [$klasse, $label]) {
        if (! Route::has($key.'.index')) {
            continue;
        }

        // Welche Fremdschluessel noetig sind, sagt die Tabelle selbst - nicht
        // jedes Geraet haengt an einem Standort oder an einem Betriebssystem.
        $spalten = Schema::getColumnListing((new $klasse)->getTable());
        $werte = ['customer_id' => $customer->id];

        foreach (['site_id' => $site->id, 'operating_system_id' => $os->id] as $spalte => $wert) {
            if (in_array($spalte, $spalten)) {
                $werte[$spalte] = $wert;
            }
        }

        try {
            $klasse::factory()->count(2)->create($werte);
        } catch (Throwable $e) {
            $ohneDaten[] = $key;
        }

        $this->get(route($key.'.index', $customer))
            ->assertOk("Liste {$key} ({$label}) rendert nicht");

        // Auch das Anlegen- und das Bearbeiten-Formular: Beim Ansprechpartner
        // war die Bearbeiten-Seite kaputt (die Routenbindung suchte eine
        // Relation, die es nicht gab), waehrend Liste und Anlegen liefen -
        // eine Luecke, die nur die Liste zu pruefen nie gezeigt haette.
        if (Route::has($key.'.create')) {
            $this->get(route($key.'.create', $customer))
                ->assertOk("Anlegen-Formular {$key} ({$label}) rendert nicht");
        }

        $eintrag = $klasse::where('customer_id', $customer->id)->first();

        if ($eintrag && Route::has($key.'.edit')) {
            $this->get(route($key.'.edit', [$customer, $eintrag]))
                ->assertOk("Bearbeiten-Formular {$key} ({$label}) rendert nicht");
        }
    }

    // Keine Liste darf mangels Testdaten nur leer geprueft werden - sonst
    // waehnt man sich in falscher Sicherheit, wie bei den Maschinen.
    expect($ohneDaten)->toBe([], 'Liste ohne Testdaten: '.implode(', ', $ohneDaten));
});
