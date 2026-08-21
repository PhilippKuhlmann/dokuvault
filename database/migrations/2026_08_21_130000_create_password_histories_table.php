<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorherige Kennwoerter - fuer den Fall, dass jemand falsch geaendert hat.
 *
 * Bewusst nicht im Aktivitaetsprotokoll: Das bleibt ewig stehen und listet alle
 * Kunden auf einer Seite. Hier stehen die alten Werte verschluesselt am Objekt,
 * sind nur mit dem Recht auf dieses Objekt sichtbar und verschwinden nach der
 * eingestellten Frist von selbst.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();

            // Nullable, weil auch Benutzerkonten eine Historie bekommen und die
            // zu keinem Kunden gehoeren.
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            // Der Name wird mitgeschrieben, nicht nachgeladen: Die Uebersicht
            // im Admin-Bereich zeigt Eintraege quer ueber alle Typen, und ein
            // Verweis auf eine entfernte Klasse bricht beim Aufloesen die
            // ganze Seite. Ausserdem soll ein Eintrag lesbar bleiben, wenn das
            // Geraet laengst weg ist.
            $table->string('subject_name')->nullable();

            // Welches Kennwort - ein Geraet hat oft mehrere (password,
            // bmcPassword, usc_pin ...).
            $table->string('field', 64);

            // TEXT, nicht VARCHAR: Ein Chiffrat misst ab 32 Zeichen Klartext
            // mehr als 255 Zeichen, auf MySQL waere das "Data too long".
            $table->text('value');

            // Wer geaendert hat. Bleibt stehen, wenn das Konto spaeter geht -
            // sonst verloere der Eintrag seine halbe Aussage.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'field'], 'password_histories_subject_index');
            // Fuer das Aufraeumen nach Frist.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
