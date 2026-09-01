<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wann zuletzt eine Einladung an diesen Zugang ging.
 *
 * Nicht aus password_resets abzulesen: Dort liegen Einladungen und
 * Kennwort-Zuruecksetzungen in derselben Tabelle, ohne Merkmal, welche Zeile
 * welche ist. Und der Eintrag verschwindet, sobald der Link eingeloest wurde -
 * die Frage "wer hat nie reagiert?" liesse sich damit nicht beantworten.
 *
 * Wird geleert, sobald der Eingeladene sein Kennwort gesetzt hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
