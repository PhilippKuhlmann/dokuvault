<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zweite Stufe der Anmeldung (TOTP).
 *
 * Beide Geheimnisse liegen verschluesselt in der Datenbank - siehe die
 * Attribute im User-Model. text statt string, weil ein verschluesselter Wert
 * ein Vielfaches seiner Klartextlaenge braucht.
 *
 * Bestaetigt wird getrennt vom Geheimnis gespeichert: zwischen "eingerichtet"
 * und "geprueft" liegt der Moment, in dem der Nutzer den ersten Code eingibt.
 * Ohne diese Trennung koennte sich jemand aussperren, dessen App das Geheimnis
 * nie richtig uebernommen hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
