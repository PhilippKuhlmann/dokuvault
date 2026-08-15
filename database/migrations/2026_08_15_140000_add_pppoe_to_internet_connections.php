<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einwahldaten am Internetanschluss.
 *
 * Bisher gab es dafuer keine Stelle - wer sie festhalten wollte, schrieb sie
 * in die Notizen, wo sie unverschluesselt liegen und in der Suche auftauchen.
 *
 * Das Passwort ist ein text: Crypt::encryptString macht aus einem kurzen
 * Kennwort mehrere hundert Zeichen, ein string(255) liefe ueber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internet_connections', function (Blueprint $table) {
            $table->string('pppoe_user')->nullable()->after('connection_type');
            $table->text('pppoe_password')->nullable()->after('pppoe_user');
        });
    }

    public function down(): void
    {
        Schema::table('internet_connections', function (Blueprint $table) {
            $table->dropColumn(['pppoe_user', 'pppoe_password']);
        });
    }
};
