<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geräte tragen kein eigenes Benutzername/Kennwort-Paar mehr. Zugangsdaten
 * stehen unter "Zugangsdaten" und werden mit dem Gerät verknüpft (siehe
 * HasCredentials) - so wie es Server und Computer schon immer taten. Zwei
 * Orte für dasselbe Kennwort hießen, beim Wechsel einen davon zu vergessen.
 *
 * Die Werte werden nicht übernommen: Es gab noch keine produktive
 * Installation, in der dort etwas stand.
 */
return new class extends Migration
{
    private const TABELLEN = [
        'firewalls', 'securepoint_umas', 'accesspoints', 'cameras', 'dect',
        'iot_devices', 'nas', 'network_switches', 'other_clients', 'phones',
        'phone_systems', 'printers', 'recorders', 'routers',
    ];

    public function up(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropColumn(['username', 'password']);
            });
        }
    }

    public function down(): void
    {
        // Nur die Spalten kommen zurück, nicht ihr Inhalt.
        foreach (self::TABELLEN as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->string('username')->nullable();
                $table->text('password')->nullable();
            });
        }
    }
};
