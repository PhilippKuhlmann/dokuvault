<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beschreibung am Katalogdienst.
 *
 * Der Name allein sagt oft zu wenig: "UMA" oder "PBS" erklaert sich nur dem,
 * der es schon kennt. Die Beschreibung steht bei der Auswahl im Geraeteformular
 * und als Titel an der Kachel in den Listen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
