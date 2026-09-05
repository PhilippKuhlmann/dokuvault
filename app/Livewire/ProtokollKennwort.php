<?php

namespace App\Livewire;

use App\Models\PasswordHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Das bisherige Kennwort in einer Protokollzeile - auf Klick.
 *
 * Der Wert steht nicht im Protokolleintrag: Der bleibt ewig stehen und listet
 * alle Kunden auf einer Seite. Er kommt aus der Kennwort-Historie und
 * verschwindet mit deren Frist. Fuer den Betrachter ist das derselbe Handgriff
 * wie bei jeder anderen Aenderung.
 */
class ProtokollKennwort extends Component
{
    /** Ids der Historie-Eintraege, die zu dieser Zeile gehoeren. */
    #[Locked]
    public array $ids = [];

    /** Beschriftungen der Felder, damit klar ist, welches Kennwort gemeint ist. */
    #[Locked]
    public array $felder = [];

    /**
     * Das Objekt, an dem die Aenderung stattfand.
     *
     * Gebraucht fuer den Fall, dass es keinen Vorgaenger gibt: Beim ersten
     * Setzen eines Kennworts ist die Historie leer, das Kennwort selbst aber
     * da. Locked, damit die Angaben aus dem Protokolleintrag stammen und nicht
     * aus dem Browser - sonst waere jede verschluesselte Spalte jedes Models
     * von aussen abfragbar.
     */
    #[Locked]
    public ?string $objektTyp = null;

    #[Locked]
    public ?int $objektId = null;

    public bool $offen = false;

    /** Geladene Werte - erst nach dem Klick gefuellt. */
    public array $werte = [];

    public function zeigen(): void
    {
        // Dasselbe Recht wie die Seite, auf der die Komponente steht: Wer das
        // Protokoll sehen darf, sieht auch, was vorher im Feld stand. Geprueft
        // wird trotzdem hier - der Aufruf kommt aus dem Browser.
        Gate::authorize('admin_activity');

        $this->werte = PasswordHistory::whereIn('id', $this->ids)
            ->get()
            ->map(fn ($eintrag) => [
                'feld' => config('custom.secret_field_labels')[$eintrag->field] ?? $eintrag->field,
                'wert' => $eintrag->value,
                'aktuell' => false,
            ])
            ->all();

        // Kein Vorgaenger: Beim ersten Setzen gibt es keinen - so entstehen
        // etwa die Kennwoerter, die ein Agent meldet. "Nichts anzuzeigen"
        // waere hier die falsche Antwort: Geaendert hat sich, dass jetzt eines
        // gilt, und genau das gehoert in die Zeile.
        if ($this->werte === []) {
            $this->werte = $this->aktuelleWerte();
        }

        $this->offen = true;
    }

    /**
     * Die Kennwoerter, die am Objekt jetzt gelten.
     *
     * Kein zusaetzlicher Einblick: Wer das Protokoll sehen darf, sieht dieselben
     * Werte eine Seite weiter in der Liste des Objekts. Hier erspart es den Weg
     * dorthin - und die Frage, ob man ueberhaupt am richtigen Eintrag ist.
     *
     * @return array<int, array{feld: string, wert: string, aktuell: bool}>
     */
    protected function aktuelleWerte(): array
    {
        if (! $this->objektTyp || ! class_exists($this->objektTyp) || ! is_subclass_of($this->objektTyp, Model::class)) {
            return [];
        }

        $abfrage = $this->objektTyp::query();

        // Im Papierkorb ist das Geraet noch da, und sein Kennwort auch - genau
        // dann sucht man es haeufig.
        if (method_exists($this->objektTyp, 'withTrashed')) {
            $abfrage->withTrashed();
        }

        $objekt = $abfrage->find($this->objektId);

        if (! $objekt) {
            return [];
        }

        return collect($this->felder)
            // Nur, was als Geheimnis gilt. Die Feldnamen stammen aus dem
            // Protokolleintrag, aber welche Spalte ein Kennwort ist,
            // entscheidet diese Liste - nicht der Eintrag.
            ->filter(fn ($feld) => in_array($feld, config('custom.secret_columns'), true))
            ->map(fn ($feld) => [
                'feld' => config('custom.secret_field_labels')[$feld] ?? $feld,
                // Ein nicht entschluesselbarer Wert (anderer APP_KEY) darf die
                // Protokollseite nicht mitreissen.
                'wert' => rescue(fn () => (string) $objekt->{$feld}, '', false),
                'aktuell' => true,
            ])
            ->filter(fn ($eintrag) => filled($eintrag['wert']))
            ->values()
            ->all();
    }

    public function verbergen(): void
    {
        $this->werte = [];
        $this->offen = false;
    }

    public function render()
    {
        return view('livewire.protokoll-kennwort');
    }
}
