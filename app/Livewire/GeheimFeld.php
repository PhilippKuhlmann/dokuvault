<?php

namespace App\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ein Geheimnis an einem Objekt - verdeckt, bis es jemand anfordert.
 *
 * Das Gegenstück zu KennwortFeld, aber für Werte, die direkt am Gerät hängen
 * (BMC-Passwort, DSRM-Passwort, Verschlüsselungscode ...) statt an einer
 * Zugangsdaten-Verknüpfung. Vorher standen sie als Klartext im ausgelieferten
 * HTML jeder Liste und jeder Karte - das Auge war nur JavaScript.
 *
 * Jetzt trägt das Feld nur Modell, Id und Feldname. Erst der Klick holt den
 * Wert über den Server, mit demselben Recht wie die Liste (viewAny), einem
 * Protokolleintrag und derselben Bremse wie KennwortFeld.
 *
 * Modell, Id und Feld sind #[Locked] und werden serverseitig gegen die
 * Geheimnis-Spalten und die Policy geprüft - über dieses Feld kommt nur
 * heraus, was ohnehin auf der Seite stand.
 */
class GeheimFeld extends Component
{
    #[Locked]
    public string $modell;

    #[Locked]
    public int $id;

    #[Locked]
    public string $feld;

    public string $width = 'w-full';

    public bool $offen = false;

    public ?string $wert = null;

    public function mount(string $modell, int $id, string $feld, string $width = 'w-full'): void
    {
        $this->modell = $modell;
        $this->id = $id;
        $this->feld = $feld;
        $this->width = $width;
    }

    public function zeigen(): void
    {
        $wert = $this->wertHolen();

        if ($wert === null) {
            return;
        }

        $this->wert = $wert;
        $this->offen = true;
    }

    /**
     * Kopiert das Geheimnis, ohne es aufzudecken: Der Wert geht einmalig an den
     * Browser (in die Zwischenablage) und steht danach nirgends im DOM.
     */
    public function kopieren(): void
    {
        $wert = $this->wertHolen();

        if ($wert === null) {
            return;
        }

        $this->dispatch('kennwort-bereit', wert: $wert)->self();
    }

    /**
     * Holt den Klartext - mit Feld-Whitelist, Rechteprüfung, Bremse und
     * Protokolleintrag. Gemeinsamer Kern von zeigen() und kopieren().
     */
    private function wertHolen(): ?string
    {
        // Feld-Whitelist: Nur echte Geheimnis-Spalten, nie ein beliebiges
        // Attribut. Modell muss ein Eloquent-Model sein.
        if (! in_array($this->feld, config('custom.secret_columns'), true)
            || ! is_subclass_of($this->modell, Model::class)) {
            return null;
        }

        // Dasselbe Recht wie die Liste/Karte, auf der das Feld steht.
        Gate::authorize('viewAny', $this->modell);

        // Dieselbe Bremse wie beim Kennwort - ein gemeinsames Budget je
        // Benutzer gegen das reihenweise Abgreifen.
        $schluessel = 'kennwort-ansehen:'.auth()->id();

        if (RateLimiter::tooManyAttempts($schluessel, config('custom.kennwort.ansehen_je_minute'))) {
            $this->addError('kennwort', __('Zu viele Kennwortabrufe in kurzer Zeit. Bitte einen Moment warten.'));

            return null;
        }

        RateLimiter::hit($schluessel, 60);

        $objekt = $this->modell::find($this->id);

        if (! $objekt) {
            return null;
        }

        activity()
            ->event('kennwort_angesehen')
            ->performedOn($objekt)
            ->causedBy(auth()->user())
            ->withProperties([
                'objekt' => $objekt->name ?? $objekt->host ?? (class_basename($objekt).' #'.$objekt->id),
                'attributes' => array_filter([
                    'Feld' => config('custom.secret_field_labels')[$this->feld] ?? $this->feld,
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('Kennwort angesehen');

        return $objekt->{$this->feld};
    }

    public function verbergen(): void
    {
        $this->wert = null;
        $this->offen = false;
    }

    public function render()
    {
        // Dieselbe Ansicht wie KennwortFeld - dasselbe verdeckte Feld mit Auge
        // und Kopierknopf.
        return view('livewire.kennwort-feld');
    }
}
