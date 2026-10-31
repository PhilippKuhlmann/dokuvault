<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wo ein Geraet haengt - "Halle 1, ueber der Tuer". Der Standort sagt,
     * in welchem Gebaeude es steht, aber nicht, wo darin; ohne diese Notiz
     * sucht man einen DECT-Sender unter der Decke wieder von Hand.
     *
     * Freitext und kein Fremdschluessel auf rooms: Diese Tabelle hat keine
     * Oberflaeche, und Serverschraenke halten ihren Ort aus demselben Grund
     * als Text (racks.location).
     */
    private const TABELLEN = ['dect', 'accesspoints', 'cameras', 'network_switches', 'iot_devices'];

    public function up(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->string('location')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELLEN as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }
    }
};
