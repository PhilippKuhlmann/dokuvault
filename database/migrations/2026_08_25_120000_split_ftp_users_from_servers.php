<?php

use App\Models\FTPServer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * FTP-Benutzer vom Server trennen.
 *
 * Bisher war eine Zeile ein Host *und* ein Benutzer. Wer drei Zugaenge auf
 * demselben Server dokumentierte, schrieb den Host dreimal - und beim
 * naechsten Umzug stand er an zwei Stellen richtig und an einer falsch.
 *
 * Jetzt: ein Server, daran beliebig viele Benutzer.
 *
 * Die vorhandenen Zeilen werden verlustfrei umgezogen. Zeilen mit demselben
 * Host beim selben Kunden werden zu einem Server zusammengefasst; weicht dabei
 * eine Beschreibung ab, wandert sie in die Notiz des Benutzers, damit nichts
 * verlorengeht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ftp_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('ftp_server_id')->constrained('ftp_servers')->onDelete('cascade');
            $table->string('username')->nullable();
            // text, nicht string: Ein verschluesselter Wert ist laenger als das
            // Kennwort und passt nicht zuverlaessig in 255 Zeichen.
            $table->text('password')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->bestandUmziehen();

        Schema::table('ftp_servers', function (Blueprint $table) {
            $table->dropColumn(['username', 'password']);
        });
    }

    /**
     * Aus jeder bisherigen Zeile wird ein Benutzer an einem Server.
     */
    private function bestandUmziehen(): void
    {
        // withTrashed: Ein Server im Papierkorb behaelt seine Zugaenge, sonst
        // stuende er nach dem Wiederherstellen ohne Benutzer da.
        $zeilen = FTPServer::withTrashed()->orderBy('id')->get();

        // Nach Kunde und Host gruppiert - gleiche Hosts werden ein Server.
        $ersterMitHost = [];

        foreach ($zeilen as $zeile) {
            $schluessel = $zeile->customer_id.'|'.trim((string) $zeile->host);
            $hatHost = trim((string) $zeile->host) !== '';

            $ziel = ($hatHost && isset($ersterMitHost[$schluessel]))
                ? $ersterMitHost[$schluessel]
                : $zeile;

            if ($hatHost && ! isset($ersterMitHost[$schluessel])) {
                $ersterMitHost[$schluessel] = $zeile;
            }

            $notiz = null;

            // Beschreibung gehoert nach der Trennung zum Server. Weicht die
            // einer zusammengefassten Zeile ab, geht sie nicht verloren.
            if ($ziel->id !== $zeile->id && filled($zeile->description) && $zeile->description !== $ziel->description) {
                $notiz = $zeile->description;
            }

            if (filled($zeile->username) || filled($this->klartext($zeile))) {
                $ziel->users()->create([
                    'customer_id' => $zeile->customer_id,
                    'username' => $zeile->username,
                    'password' => $this->klartext($zeile),
                    'note' => $notiz,
                ]);
            }

            // Die zusammengefasste Zeile faellt weg - ihr Inhalt steht jetzt
            // beim ersten Server desselben Hosts.
            if ($ziel->id !== $zeile->id) {
                $zeile->forceDelete();
            }
        }
    }

    /**
     * Das Kennwort im Klartext.
     *
     * Ausdruecklich entschluesseln statt ueber den Accessor: Den hat FTPServer
     * nach der Trennung nicht mehr - er sitzt jetzt bei FTPUser. Ueber das
     * Model gelesen kaeme der verschluesselte Wert heraus, und FTPUser wuerde
     * ihn ein zweites Mal verschluesseln. Das Kennwort waere damit verloren.
     *
     * Ein Wert, der sich nicht entschluesseln laesst (von Hand eingetragen,
     * aus einem alten Stand), wird unveraendert uebernommen statt die
     * Migration abbrechen zu lassen.
     */
    private function klartext(FTPServer $zeile): ?string
    {
        $roh = $zeile->getRawOriginal('password');

        if (blank($roh)) {
            return null;
        }

        try {
            return Crypt::decryptString($roh);
        } catch (Throwable) {
            return $roh;
        }
    }

    public function down(): void
    {
        Schema::table('ftp_servers', function (Blueprint $table) {
            $table->string('username')->nullable();
            $table->text('password')->nullable();
        });

        Schema::dropIfExists('ftp_users');
    }
};
