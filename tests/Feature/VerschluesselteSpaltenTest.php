<?php

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Findet die verschluesselten Felder selbst, statt eine Liste zu pflegen: Ein
 * neues Kennwortfeld faellt damit auf, sobald es angelegt wird - und nicht
 * erst, wenn jemand ein langes Kennwort eintraegt und die Anwendung abbricht.
 */
function verschluesselteSpalten(): array
{
    $treffer = [];

    foreach (glob(app_path('Models/*.php')) as $datei) {
        $klasse = 'App\\Models\\'.basename($datei, '.php');

        if (! class_exists($klasse) || ! is_subclass_of($klasse, Model::class)) {
            continue;
        }

        $spiegel = new ReflectionClass($klasse);
        $zeilen = file($datei);

        foreach ($spiegel->getMethods(ReflectionMethod::IS_PROTECTED) as $methode) {
            if ($methode->getDeclaringClass()->getName() !== $klasse) {
                continue;
            }

            if ((string) $methode->getReturnType() !== Attribute::class) {
                continue;
            }

            $koerper = implode('', array_slice(
                $zeilen,
                $methode->getStartLine() - 1,
                $methode->getEndLine() - $methode->getStartLine() + 1
            ));

            // Nur die, die wirklich verschluesseln - services() und
            // bandwidthDown() sind auch Attribute, aber harmlos.
            if (! str_contains($koerper, 'Crypt::encryptString')) {
                continue;
            }

            $tabelle = (new $klasse)->getTable();
            $spalten = Schema::getColumnListing($tabelle);

            // Die Spalte heisst mal wie die Methode (bmcPassword), mal in
            // Unterstrich-Schreibweise (pppoe_password).
            $spalte = in_array($methode->getName(), $spalten)
                ? $methode->getName()
                : Str::snake($methode->getName());

            $treffer[] = [$klasse, $tabelle, $spalte];
        }
    }

    return $treffer;
}

test('jedes verschluesselte Feld hat eine Spalte', function () {
    foreach (verschluesselteSpalten() as [$klasse, $tabelle, $spalte]) {
        // Genau hier lag der Fehler beim DSRM-Kennwort: Der Accessor hiess
        // password(), die Spalte dsrmpassword - er lief ins Leere und das
        // Kennwort stand im Klartext in der Datenbank.
        expect(Schema::hasColumn($tabelle, $spalte))
            ->toBeTrue("{$klasse}: Accessor ohne passende Spalte {$tabelle}.{$spalte}");
    }
});

test('keine verschluesselte Spalte ist auf 255 Zeichen begrenzt', function () {
    $zuKlein = [];

    foreach (verschluesselteSpalten() as [$klasse, $tabelle, $spalte]) {
        $typ = collect(Schema::getColumns($tabelle))->firstWhere('name', $spalte);

        // Ein Chiffrat misst ab 32 Zeichen Klartext mehr als 255 Zeichen. Auf
        // MySQL heisst das "Data too long for column" - hier auf SQLite faellt
        // es nicht auf, deshalb prueft der Test den Typ statt einen Schreibversuch.
        if ($typ && str_contains(strtolower($typ['type']), 'varchar')) {
            $zuKlein[] = "{$tabelle}.{$spalte} ({$typ['type']})";
        }
    }

    expect($zuKlein)->toBe([], 'Zu kurze Spalten: '.implode(', ', $zuKlein));
});
