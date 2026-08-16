<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DSRM-Kennwoerter verschluesseln.
 *
 * Das Model trug einen Accessor namens password(), eine Spalte dieses Namens
 * gibt es in ad_domains aber nicht - die Verschluesselung lief also ins Leere
 * und die Kennwoerter standen im Klartext in der Datenbank. Model und Spalte
 * sind jetzt aufeinander abgestimmt; hier kommt der Bestand nach.
 *
 * Die Spalte wird dafuer zu text: Ein Chiffrat misst schon fuer ein kurzes
 * Kennwort rund 200 Zeichen, bei den 255 erlaubten Eingabezeichen sind es
 * ueber 600. In varchar(255) waere es abgeschnitten und damit unbrauchbar -
 * und zwar still, ohne Fehlermeldung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_domains', function (Blueprint $table) {
            $table->text('dsrmpassword')->change();
        });

        $this->jedeZeile(function (object $zeile) {
            // Schon verschluesselt? Dann in Ruhe lassen. Die Migration darf
            // zweimal laufen, ohne ein Chiffrat noch einmal zu verpacken.
            if ($this->istVerschluesselt($zeile->dsrmpassword)) {
                return;
            }

            DB::table('ad_domains')->where('id', $zeile->id)->update([
                'dsrmpassword' => Crypt::encryptString($zeile->dsrmpassword),
            ]);
        });
    }

    public function down(): void
    {
        // Zuerst entschluesseln, sonst passt der Wert nicht mehr in varchar(255).
        $this->jedeZeile(function (object $zeile) {
            if (! $this->istVerschluesselt($zeile->dsrmpassword)) {
                return;
            }

            DB::table('ad_domains')->where('id', $zeile->id)->update([
                'dsrmpassword' => Crypt::decryptString($zeile->dsrmpassword),
            ]);
        });

        Schema::table('ad_domains', function (Blueprint $table) {
            $table->string('dsrmpassword')->change();
        });
    }

    /**
     * Alle Zeilen mit gefuelltem Kennwort, in Haeppchen - auch geloeschte:
     * Ein Eintrag aus dem Papierkorb laesst sich wiederherstellen und stuende
     * sonst als einziger noch im Klartext da.
     */
    private function jedeZeile(callable $arbeit): void
    {
        DB::table('ad_domains')
            ->whereNotNull('dsrmpassword')
            ->where('dsrmpassword', '<>', '')
            ->orderBy('id')
            ->chunkById(200, fn ($zeilen) => $zeilen->each($arbeit));
    }

    private function istVerschluesselt(?string $wert): bool
    {
        if (blank($wert)) {
            return false;
        }

        try {
            Crypt::decryptString($wert);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
