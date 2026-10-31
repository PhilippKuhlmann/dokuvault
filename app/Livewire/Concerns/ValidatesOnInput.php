<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;

/**
 * A form that checks while typing - but only from the first rejected submit
 * onwards.
 *
 * The timing is the whole point. Checking live always would be worse than not
 * at all: whoever has typed "10." would read "please enter a network" although
 * they are in the middle of it. After a rejected submit it is the other way
 * round - a red border already stands there, and it should disappear as soon
 * as the value is right.
 *
 * What a component has to do for it:
 *
 *   1. regeln() liefern - dieselben, mit denen sie beim Speichern prueft. Zwei
 *      Listen liefen frueher oder spaeter auseinander.
 *   2. Call enableCheck() before the validate() in save.
 *   3. Beim Leeren des Formulars "geprueft" mit zuruecksetzen.
 *
 * If the component has an updated() of its own, that wins over this one - then
 * it has to call validateOnInput() itself. A test keeps that open, or the
 * check fails silently.
 */
trait ValidatesOnInput
{
    /** Has it been submitted once? Only then is it checked while typing. */
    public bool $geprueft = false;

    /** The rules that saving checks with as well. */
    abstract protected function regeln(): array;

    /** Labels for the messages - "Bitte Bezeichnung angeben.". */
    protected function fieldNames(): array
    {
        return [];
    }

    public function updated(string $eigenschaft): void
    {
        $this->validateOnInput($eigenschaft);
    }

    /**
     * From now on every input is checked.
     *
     * Belongs before the validate() in save, not after it: validate() throws
     * on an error, and that is exactly when it should be switched on.
     */
    protected function enableCheck(): void
    {
        $this->geprueft = true;
    }

    /**
     * Check a field when the time has come.
     *
     * Only fields that have a rule: a form has more properties than fields -
     * open, editedId, search texts - and those do not go through the check
     * here.
     */
    protected function validateOnInput(string $eigenschaft): void
    {
        if (! $this->geprueft) {
            return;
        }

        $regeln = $this->regeln();

        // Str::is because of the placeholders: a patch panel has "outlet.*" as
        // a rule and reports "outlet.7" as changed. Without the comparison the
        // check would fail silently there.
        $passt = collect($regeln)->keys()->contains(
            fn ($muster) => $muster === $eigenschaft || Str::is($muster, $eigenschaft)
        );

        if (! $passt) {
            return;
        }

        $this->validateOnly($eigenschaft, $regeln, [], $this->fieldNames());
    }
}
