<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Indizes auf die Spalten, die die globale Suche durchsucht.
 *
 * Zusammen mit der Umstellung von "%begriff%" auf "begriff%" ist das der Punkt,
 * an dem die Suche bei Masse benutzbar bleibt: Gemessen an 4 Millionen
 * AD-Benutzern brauchte die alte Form 2788 ms, die neue auf einer indizierten
 * Spalte 4 ms. Ohne Index bringt die Umstellung nichts - MySQL liest dann
 * weiter die ganze Tabelle.
 *
 * Nur die grossen Tabellen: Bei ein paar tausend Zeilen ist ein zusaetzlicher
 * Index Aufwand beim Schreiben ohne messbaren Gewinn beim Lesen.
 */
return new class extends Migration
{
    /** Tabelle => Spalten, wie sie in App\Livewire\GlobalSearch durchsucht werden. */
    private const FELDER = [
        'ad_users' => ['username', 'firstName', 'lastName', 'email'],
        'computers' => ['name', 'serialNumber'],
        'vms' => ['name'],
        'servers' => ['name', 'serialNumber'],
        'phones' => ['serialNumber'],
        'cameras' => ['name', 'serialNumber'],
        'printers' => ['name', 'serialNumber'],
        'accesspoints' => ['name', 'serialNumber'],
        'network_switches' => ['name', 'serialNumber'],
        'ip_addresses' => ['address'],
    ];

    public function up(): void
    {
        $this->jedeSpalte(function (string $tabelle, string $spalte, string $index) {
            if (! $this->hatIndex($tabelle, $index)) {
                Schema::table($tabelle, fn ($t) => $t->index($spalte, $index));
            }
        });
    }

    public function down(): void
    {
        $this->jedeSpalte(function (string $tabelle, string $spalte, string $index) {
            if ($this->hatIndex($tabelle, $index)) {
                Schema::table($tabelle, fn ($t) => $t->dropIndex($index));
            }
        });
    }

    private function jedeSpalte(callable $arbeit): void
    {
        foreach (self::FELDER as $tabelle => $spalten) {
            if (! Schema::hasTable($tabelle)) {
                continue;
            }

            foreach ($spalten as $spalte) {
                if (Schema::hasColumn($tabelle, $spalte)) {
                    // Eigener Name statt des automatischen: Der waere je
                    // Datenbank anders und liesse sich nicht zuverlaessig
                    // wieder entfernen.
                    $arbeit($tabelle, $spalte, 'suche_'.$tabelle.'_'.strtolower($spalte));
                }
            }
        }
    }

    /**
     * Gibt es den Index schon? Ein zweiter Lauf soll nicht abbrechen.
     */
    private function hatIndex(string $tabelle, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($tabelle))
                ->contains(fn ($i) => strtolower($i['name']) === strtolower($index));
        } catch (Throwable) {
            return false;
        }
    }
};
