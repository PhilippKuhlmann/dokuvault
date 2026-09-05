<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VLAN und Kennwort eines WLANs duerfen fehlen.
 *
 * Der UniFi-Agent findet am Controller ein WLAN mit SSID und Verschluesselung
 * vor. Welches der in DokuVault gepflegten VLANs dahintersteht, weiss er
 * nicht - und das Kennwort liest er bewusst nicht aus, wie der AD-Agent
 * Kennwoerter nie ausliest. Beide Spalten waren NOT NULL; ein gefundenes WLAN
 * haette sich also nur anlegen lassen, indem der Agent etwas erfindet.
 *
 * Am Formular aendert sich nichts: WifiRequest verlangt beide Felder weiter,
 * und der Dokumentations-Assistent tut es ueber RULE_OVERRIDES ebenfalls. Wer
 * ein WLAN von Hand eintraegt, wird also weiter nach VLAN und Kennwort
 * gefragt. Nur die Datenbank besteht nicht mehr darauf, wo es niemand wissen
 * kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wifis', function (Blueprint $table) {
            $table->unsignedBigInteger('network_id')->nullable()->change();
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wifis', function (Blueprint $table) {
            $table->unsignedBigInteger('network_id')->nullable(false)->change();
            $table->text('password')->nullable(false)->change();
        });
    }
};
