<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funktion des Ansprechpartners.
 *
 * Bisher standen nur Name, Telefon und E-Mail da. Bei drei Kontakten weiss
 * hinterher niemand mehr, wer die Geschaeftsfuehrung ist, wer die IT betreut
 * und wen man beim Lager anruft - genau das braucht man aber, bevor man
 * jemanden waehlt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_people', function (Blueprint $table) {
            $table->string('role')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('contact_people', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
