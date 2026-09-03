<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;

/**
 * Ein Formular, das waehrend der Eingabe prueft - aber erst ab dem ersten
 * abgewiesenen Absenden.
 *
 * Der Zeitpunkt ist der Punkt der Sache. Immer live zu pruefen waere schlechter
 * als gar nicht: Wer "10." getippt hat, bekaeme "Bitte Netz angeben" zu lesen,
 * obwohl er gerade mittendrin ist. Nach einem abgewiesenen Absenden ist es
 * umgekehrt richtig - dort steht schon ein roter Rahmen, und der soll
 * verschwinden, sobald der Wert stimmt.
 *
 * Was eine Komponente dafuer tun muss:
 *
 *   1. regeln() liefern - dieselben, mit denen sie beim Speichern prueft. Zwei
 *      Listen liefen frueher oder spaeter auseinander.
 *   2. Vor dem validate() im Speichern pruefungEinschalten() rufen.
 *   3. Beim Leeren des Formulars "geprueft" mit zuruecksetzen.
 *
 * Hat die Komponente ein eigenes updated(), gewinnt es gegen das hier - dann
 * muss es waehrendDerEingabePruefen() selbst rufen. Ein Test haelt das offen,
 * sonst faellt die Pruefung still aus.
 */
trait PrueftWaehrendDerEingabe
{
    /** Ist einmal abgeschickt worden? Erst dann wird waehrend der Eingabe geprueft. */
    public bool $geprueft = false;

    /** Die Regeln, mit denen auch das Speichern prueft. */
    abstract protected function regeln(): array;

    /** Beschriftungen fuer die Meldungen - "Bitte Bezeichnung angeben.". */
    protected function feldnamen(): array
    {
        return [];
    }

    public function updated(string $eigenschaft): void
    {
        $this->waehrendDerEingabePruefen($eigenschaft);
    }

    /**
     * Ab jetzt wird bei jeder Eingabe geprueft.
     *
     * Gehoert vor das validate() im Speichern, nicht dahinter: validate() wirft
     * bei einem Fehler, und genau dann soll es eingeschaltet sein.
     */
    protected function pruefungEinschalten(): void
    {
        $this->geprueft = true;
    }

    /**
     * Ein Feld pruefen, wenn es an der Zeit ist.
     *
     * Nur Felder, fuer die es eine Regel gibt: Ein Formular hat mehr
     * Eigenschaften als Felder - offen, bearbeiteId, Suchtexte -, und die
     * gehen hier nicht durch die Pruefung.
     */
    protected function waehrendDerEingabePruefen(string $eigenschaft): void
    {
        if (! $this->geprueft) {
            return;
        }

        $regeln = $this->regeln();

        // Str::is wegen der Platzhalter: Ein Patchfeld hat "outlet.*" als Regel
        // und meldet "outlet.7" als geaendert. Ohne den Abgleich fiele die
        // Pruefung dort still aus.
        $passt = collect($regeln)->keys()->contains(
            fn ($muster) => $muster === $eigenschaft || Str::is($muster, $eigenschaft)
        );

        if (! $passt) {
            return;
        }

        $this->validateOnly($eigenschaft, $regeln, [], $this->feldnamen());
    }
}
