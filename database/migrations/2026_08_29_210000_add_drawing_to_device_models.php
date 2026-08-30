<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine eigene Zeichnung fuer dieses Modell.
     *
     * Die gezeichnete Blende des Geraetetyps trifft die Bauart, aber
     * nicht das Geraet: Ein Switch sieht aus wie ein Switch. Wo es sich lohnt,
     * bekommt ein Modell stattdessen eine eigene Zeichnung - von Hand gemacht
     * und im Projekt abgelegt, nicht hochgeladen.
     *
     * Der Wert ist ein Schluessel aus config('custom.rack_model_drawings'),
     * nie ein Dateiname: Sonst waere der Name einer Blade-Ansicht von aussen
     * bestimmbar.
     */
    public function up(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->string('drawing', 60)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropColumn('drawing');
        });
    }
};
