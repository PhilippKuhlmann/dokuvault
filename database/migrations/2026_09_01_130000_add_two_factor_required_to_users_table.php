<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ob ein Administrator die zweite Stufe fuer diesen Zugang verlangt.
 *
 * Getrennt von two_factor_confirmed_at, weil beides unterschiedliche Fragen
 * beantwortet: "muss er" und "hat er". Zwischen dem Setzen des Hakens und der
 * fertigen Einrichtung liegt der Weg, den die Middleware erzwingt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_required')->default(false)->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_required');
        });
    }
};
