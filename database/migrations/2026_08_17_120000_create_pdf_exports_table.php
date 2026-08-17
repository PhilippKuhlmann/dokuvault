<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auftraege fuer die PDF-Ausgabe.
 *
 * Das PDF entsteht nicht mehr im Request: Bei einem Kunden mit 40 Servern, 90
 * VMs und 160 Computern braucht DomPDF 370 MB und 15 Sekunden - das reicht an
 * jedes uebliche Zeitlimit heran, und der Bedarf waechst mit dem Kunden.
 * Stattdessen wird hier ein Auftrag hinterlegt, den der Scheduler abarbeitet;
 * die Seite fragt den Stand ab und bietet die fertige Datei zum Laden an.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Wer den Auftrag gestellt hat: Das PDF enthaelt alle Zugangsdaten
            // des Kunden, es darf also nur der Besteller es abholen.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('offen');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Der haeufigste Zugriff: der letzte Auftrag dieses Nutzers zu
            // diesem Kunden.
            $table->index(['customer_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_exports');
    }
};
