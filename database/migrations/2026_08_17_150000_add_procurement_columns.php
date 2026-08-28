<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beschaffung und Garantie fuer jedes Stueck Hardware.
 *
 * Bis hierhin liess sich zu einem Server die Seriennummer erfassen - aber nicht,
 * wann er gekauft wurde, wie lange er Garantie hat und bei wem er bestellt
 * wurde. Damit blieben genau die zwei Fragen offen, fuer die man die
 * Seriennummer ueberhaupt notiert: "Ist die Kiste noch in Garantie?" und "Wo
 * haben wir die her?".
 *
 * Die Tabellenliste ist nicht handverlesen, sondern folgt einem Merkmal: Wo eine
 * serialNumber steht, ist beschaffte Hardware dokumentiert. VMs, Netze und
 * Konten haben keine und bleiben aussen vor - eine VM hat keine Garantie.
 */
return new class extends Migration
{
    /**
     * Alle Tabellen mit serialNumber, dazu die neue Firewall.
     */
    private const TABELLEN = [
        'accesspoints',
        'cameras',
        'computers',
        'dect',
        'firewalls',
        'iot_devices',
        'nas',
        'network_switches',
        'other_clients',
        'phone_systems',
        'phones',
        'printers',
        'recorders',
        'routers',
        'servers',
        'ups',
    ];

    private const SPALTEN = ['purchase_date', 'warranty_until', 'eol_date', 'supplier'];

    public function up(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            if (! Schema::hasTable($tabelle)) {
                continue;
            }

            // Spaltenweise pruefen: Ein zweiter Lauf soll nicht abbrechen, und
            // eine Installation kann einzelne Tabellen nicht haben.
            $fehlende = array_values(array_filter(
                self::SPALTEN,
                fn ($spalte) => ! Schema::hasColumn($tabelle, $spalte)
            ));

            if ($fehlende === []) {
                continue;
            }

            Schema::table($tabelle, function (Blueprint $table) use ($fehlende) {
                foreach ($fehlende as $spalte) {
                    if ($spalte === 'supplier') {
                        $table->string('supplier')->nullable();
                    } else {
                        $table->date($spalte)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            if (! Schema::hasTable($tabelle)) {
                continue;
            }

            $vorhandene = array_values(array_filter(
                self::SPALTEN,
                fn ($spalte) => Schema::hasColumn($tabelle, $spalte)
            ));

            if ($vorhandene === []) {
                continue;
            }

            Schema::table($tabelle, fn (Blueprint $table) => $table->dropColumn($vorhandene));
        }
    }
};
