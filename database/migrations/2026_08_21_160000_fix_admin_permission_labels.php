<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Beschriftungen der Admin-Rechte nachziehen.
 *
 * Sie standen in der Rollenverwaltung ohne Umlaute da - "Auswahlmenues
 * verwalten", "Papierkorb ueber alle Kunden". In den Kommentaren des
 * Quelltextes ist das die Hausschreibweise, auf dem Bildschirm ist es falsch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (array_merge(config('custom.admin_permissions'), config('custom.extra_permissions')) as $name => $beschreibung) {
            $recht = Permission::where('name', $name)->first();

            $recht?->forceFill(['description' => $beschreibung])->save();
        }
    }

    /**
     * Eine Beschriftung zurueckzudrehen brächte niemandem etwas.
     */
    public function down(): void {}
};
