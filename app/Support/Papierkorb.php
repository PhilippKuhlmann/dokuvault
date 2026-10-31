<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Der Papierkorb eines Kunden: was geloescht wurde und sich zurueckholen
 * laesst.
 *
 * Einmal gerechnet, zweimal gezeigt - im Browser (TrashController) und in
 * der iOS-App (Mobile\TrashController). Die Arten kommen aus
 * config('custom.trashables'), nie aus der Eingabe: Der Typ in der Adresse
 * ist ein Schluessel dieser Liste, sonst 404.
 */
class Papierkorb
{
    /**
     * Wie viele Eintraege je Art hoechstens.
     *
     * Was abgeschnitten wird, meldet `gekuerzt` - ein stilles Abschneiden
     * liest sich wie "mehr ist nicht da".
     */
    public const JE_ART = 100;

    /** Woran man ein geloeschtes Objekt wiedererkennt, Spalte fuer Spalte. */
    private const NAMENSSPALTEN = ['name', 'ssid', 'username', 'mailAdress', 'domain', 'provider', 'description', 'host'];

    public function __construct(
        private Customer $kunde,
        private User $nutzer,
    ) {}

    /** Ob der Angemeldete ueberhaupt in den Papierkorb sehen darf. */
    public function darfSehen(): bool
    {
        return Gate::forUser($this->nutzer)->allows('see_hidden');
    }

    /**
     * Was im Papierkorb liegt, das Neueste zuerst.
     *
     * Nur Arten, die dieser Nutzer sehen darf - sonst stuenden hier Namen von
     * Objekten, von denen er nichts wissen soll.
     *
     * @return Collection<int, array{typ: string, titel: string, name: string, geloescht_am: mixed, id: int, darf_wiederherstellen: bool}>
     */
    public function eintraege(): Collection
    {
        $eintraege = collect();

        foreach ($this->arten() as $slug => [$klasse, $bezeichnung]) {
            $geloescht = $klasse::onlyTrashed()
                ->where('customer_id', $this->kunde->id)
                ->orderByDesc('deleted_at')
                ->limit(self::JE_ART)
                ->get();

            foreach ($geloescht as $objekt) {
                $eintraege->push([
                    'typ' => $slug,
                    'titel' => __($bezeichnung),
                    'name' => $this->name($objekt),
                    'geloescht_am' => $objekt->deleted_at,
                    'id' => $objekt->id,
                    'darf_wiederherstellen' => $this->darfWiederherstellen($slug),
                ]);
            }
        }

        return $eintraege->sortByDesc('geloescht_am')->values();
    }

    /**
     * Welche Arten mehr haben, als die Liste zeigt.
     *
     * @return array<string, int> Bezeichnung => wie viele es wirklich sind
     */
    public function gekuerzt(): array
    {
        $gekuerzt = [];

        foreach ($this->arten() as $slug => [$klasse, $bezeichnung]) {
            $vorhanden = $klasse::onlyTrashed()->where('customer_id', $this->kunde->id)->count();

            if ($vorhanden > self::JE_ART) {
                $gekuerzt[$bezeichnung] = $vorhanden;
            }
        }

        return $gekuerzt;
    }

    /**
     * Ein Objekt zurueckholen.
     *
     * Das braucht das Recht zu loeschen, nicht nur das, in den Papierkorb zu
     * sehen: Wiederherstellen macht ein Loeschen rueckgaengig.
     */
    public function wiederherstellen(string $typ, int $id): Model
    {
        // Die Klasse kommt aus der Liste der Konfiguration, nie aus der
        // Eingabe.
        $eintrag = config("custom.trashables.$typ");
        abort_unless($eintrag, 404);
        [$klasse] = $eintrag;

        Gate::forUser($this->nutzer)->authorize($typ.'_delete');

        // Am Kunden festgemacht: kein Zurueckholen ueber Kundengrenzen (IDOR).
        $objekt = $klasse::onlyTrashed()
            ->where('customer_id', $this->kunde->id)
            ->findOrFail($id);

        $objekt->restore();

        return $objekt;
    }

    public function darfWiederherstellen(string $typ): bool
    {
        return Gate::forUser($this->nutzer)->allows($typ.'_delete');
    }

    /**
     * Die Arten, die dieser Nutzer sehen darf.
     *
     * @return array<string, array{0: class-string<Model>, 1: string}>
     */
    private function arten(): array
    {
        return array_filter(
            config('custom.trashables'),
            fn ($eintrag, $slug) => Gate::forUser($this->nutzer)->allows($slug.'_viewAny'),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function name(Model $objekt): string
    {
        foreach (self::NAMENSSPALTEN as $spalte) {
            $wert = $objekt->getAttribute($spalte);

            if (is_string($wert) && $wert !== '') {
                return $wert;
            }
        }

        return '#'.$objekt->getKey();
    }
}
