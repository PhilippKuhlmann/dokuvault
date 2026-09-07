<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Server und VMs duerfen ohne Betriebssystem auskommen.
 *
 * Gedacht war die Spalte immer als optional - die urspruengliche Migration
 * schrieb ->constrained()->nullable(), und das nullable() landete am
 * Fremdschluessel statt an der Spalte. Uebrig blieb NOT NULL. Aufgefallen ist
 * es erst jetzt, weil bis dahin immer irgendein Wert eingetragen wurde.
 *
 * Genau das soll aufhoeren: Der Proxmox-Agent legt keine Katalogeintraege mehr
 * an. Er meldet, was im Gast steht ('debian', '12'), und wenn sich daraus kein
 * vorhandener Eintrag ergibt, bleibt das Feld leer. Ein leeres Feld sagt "hier
 * muss noch jemand hinschauen"; ein Sammel-Eintrag "Linux" sagt gar nichts und
 * hat kein Support-Ende. 'computers' hat es von Anfang an so gehalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['servers', 'vms'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->foreignId('operating_system_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Zurueck auf NOT NULL geht nur, solange nirgends null steht. Ein
        // Betriebssystem zu erfinden oder Server zu loeschen waere schlimmer
        // als ein fehlgeschlagenes Zurueckrollen.
        foreach (['servers', 'vms'] as $tabelle) {
            if (DB::table($tabelle)->whereNull('operating_system_id')->exists()) {
                throw new RuntimeException(
                    "In '$tabelle' stehen Zeilen ohne Betriebssystem. Erst zuordnen, dann zurueckrollen."
                );
            }
        }

        foreach (['servers', 'vms'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->foreignId('operating_system_id')->nullable(false)->change();
            });
        }
    }
};
