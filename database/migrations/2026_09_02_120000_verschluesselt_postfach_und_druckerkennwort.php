<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kennwörter und Lizenzschlüssel, die im Klartext in der Datenbank lagen.
 *
 * Gefunden beim Abgleich aller Spalten, deren Name auf ein Geheimnis hindeutet,
 * mit denen, die tatsächlich einen Crypt-Accessor haben: Diese beiden waren die
 * einzigen Kennwörter ohne. Ein Datenbank-Abzug enthielt sie lesbar.
 *
 * Die Lizenzschlüssel bleiben bewusst im Klartext - siehe die Begründung im
 * Test RuhezustandTest: Nach ihnen wird gesucht, und über eine verschlüsselte
 * Spalte lässt sich nicht suchen.
 *
 * Zwei Schritte, in dieser Reihenfolge: Erst wird die Spalte groß genug für ein
 * Chiffrat (varchar(255) reicht ab etwa 32 Zeichen Klartext nicht mehr - auf
 * MySQL wäre das ein "Data too long", auf SQLite ein stilles Abschneiden),
 * dann werden die vorhandenen Werte verschlüsselt.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> */
    private array $spalten = [
        ['mailboxes', 'password'],
        ['printers', 'password'],
    ];

    public function up(): void
    {
        foreach ($this->spalten as [$tabelle, $spalte]) {
            if (! Schema::hasColumn($tabelle, $spalte)) {
                continue;
            }

            Schema::table($tabelle, function (Blueprint $tab) use ($spalte) {
                $tab->text($spalte)->nullable()->change();
            });

            // Ueber die Query-Fassade, nicht ueber das Model: Der Accessor
            // wuerde den Klartext beim Lesen zu entschluesseln versuchen.
            DB::table($tabelle)->select('id', $spalte)->orderBy('id')->chunk(200,
                function ($zeilen) use ($tabelle, $spalte) {
                    foreach ($zeilen as $zeile) {
                        $wert = $zeile->{$spalte};

                        if ($wert === null || $wert === '' || $this->bereitsVerschluesselt($wert)) {
                            continue;
                        }

                        DB::table($tabelle)->where('id', $zeile->id)
                            ->update([$spalte => Crypt::encryptString($wert)]);
                    }
                });
        }
    }

    /**
     * Damit ein zweiter Lauf nichts doppelt verschlüsselt - etwa wenn die
     * Migration abbricht und wiederholt wird.
     */
    private function bereitsVerschluesselt(string $wert): bool
    {
        try {
            Crypt::decryptString($wert);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Bewusst ohne Entschlüsselung: Ein Rückweg, der Kennwörter wieder im
     * Klartext ablegt, ist keiner. Der Spaltentyp bleibt, er stört nicht.
     */
    public function down(): void
    {
        //
    }
};
