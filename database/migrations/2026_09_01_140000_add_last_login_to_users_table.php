<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wann sich ein Benutzer zuletzt angemeldet hat.
 *
 * Steht als Spalte am Benutzer und nicht nur im Protokoll: Die Frage "wer
 * benutzt diesen Zugang eigentlich noch?" beantwortet man in einer Liste, und
 * dafuer muesste man sonst fuer jede Zeile das Protokoll durchsuchen. Das
 * Protokoll behaelt die Geschichte, hier steht der letzte Stand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('two_factor_required');
            // IPv6 braucht bis zu 45 Zeichen.
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'last_login_ip']);
        });
    }
};
