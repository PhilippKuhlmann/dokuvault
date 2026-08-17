<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Die Securepoint UTM geht in der Firewall auf.
 *
 * Kein Datenumzug: Die Anwendung ist noch nicht produktiv, die vorhandenen
 * Datensaetze sind Demo-Daten und entstehen beim naechsten Seeder-Lauf als
 * Firewall mit Hersteller Securepoint neu.
 *
 * Die Migration ist trotzdem noetig, weil die Tabelle und die vier
 * Berechtigungen auf schon migrierten Installationen liegen - auf der Demo
 * etwa. Ohne sie blieben eine tote Tabelle und vier Rechte stehen, die in der
 * Rollenverwaltung auf eine Seite zeigen, die es nicht mehr gibt.
 */
return new class extends Migration
{
    /** Polymorphe Verweise: Tabelle => Spalte mit dem Klassennamen. */
    private const VERWEISE = [
        'ip_addresses' => 'ipable_type',
        'credential_links' => 'credentialable_type',
        'rack_items' => 'device_type',
        'activity_log' => 'subject_type',
    ];

    public function up(): void
    {
        $this->verwaisteVerweiseEntfernen();

        Schema::dropIfExists('securepoint_utms');

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->where('name', 'like', 'securepointutm_%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    /**
     * IP-Adressen, Zugangsdaten, Schrankeinbauten und Protokoll zeigen mit einem
     * Klassennamen auf das Geraet. Bleibt "App\\Models\\SecurepointUTM" stehen,
     * bricht jede Seite, die den Verweis aufloest, mit "Class not found" - auf
     * der Demo waere das die Zugangsdaten-Seite und der Verlauf. Nachgemessen,
     * nicht vermutet.
     *
     * Geloescht statt umgebogen: Die alten Ids zeigen auf Datensaetze, die es
     * nicht mehr gibt. Auf die Firewall umgeschrieben, wuerden sie zufaellig auf
     * ein fremdes Geraet mit derselben Id zeigen - schlimmer als ein fehlender
     * Eintrag.
     */
    private function verwaisteVerweiseEntfernen(): void
    {
        foreach (self::VERWEISE as $tabelle => $spalte) {
            if (Schema::hasTable($tabelle) && Schema::hasColumn($tabelle, $spalte)) {
                DB::table($tabelle)->where($spalte, 'App\\Models\\SecurepointUTM')->delete();
            }
        }
    }

    public function down(): void
    {
        // Bewusst leer: Die Tabelle wieder anzulegen brächte eine leere Hülle
        // zurück, kein Datenbestand. Wer den alten Stand braucht, geht über die
        // ursprüngliche create-Migration.
    }
};
