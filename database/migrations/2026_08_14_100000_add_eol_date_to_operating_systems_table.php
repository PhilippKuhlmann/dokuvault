<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ende des Supports je Betriebssystem.
 *
 * Am Betriebssystem gepflegt, nicht am Gerät: Das Datum gilt für alle Server
 * und VMs, die darauf laufen - einmal eintragen statt an hundert Geräten.
 * Nullable, weil es nicht für jedes System bekannt ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operating_systems', function (Blueprint $table) {
            $table->date('eol_date')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('operating_systems', function (Blueprint $table) {
            $table->dropColumn('eol_date');
        });
    }
};
