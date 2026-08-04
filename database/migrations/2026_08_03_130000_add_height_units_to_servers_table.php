<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Der Rack-Editor liest height_units beim Einbau. Ohne die Spalte
            // bekam jeder Server eine Hoeheneinheit, unabhaengig von der
            // Bauhoehe. Standard 1, weil das der haeufigste Fall ist.
            $table->unsignedTinyInteger('height_units')->default(1)->after('full_depth');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('height_units');
        });
    }
};
