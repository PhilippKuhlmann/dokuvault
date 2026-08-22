<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fehlte bisher: Server und VM bekamen agent_identifier schon mit dem
 * urspruenglichen Agent-Token-Feature (2026_07_21_130100), Computer nicht -
 * der Windows-Client-Agent braucht die Spalte fuer sein Upsert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->string('agent_identifier')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropColumn('agent_identifier');
        });
    }
};
