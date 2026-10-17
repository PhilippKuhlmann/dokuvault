<?php

namespace App\Livewire;

use App\Exceptions\ZuVieleKennwortabrufe;
use App\Models\CredentialLink;
use App\Support\Kennwortabruf;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ein Kennwort in einer Geräteliste - verdeckt, bis es jemand anfordert.
 *
 * Vorher stand der Klartext im ausgelieferten HTML: Das Auge war reines
 * JavaScript, der Wert lag längst im DOM. Wer die Liste öffnete, hatte damit
 * alle Kennwörter, ohne einen Klick und ohne dass es irgendwo stand.
 *
 * Jetzt trägt das Feld nur die Verknüpfung, nicht den Wert. Erst der Klick holt
 * ihn - über den Server, mit demselben Recht wie die Liste, mit einem Eintrag
 * im Protokoll und einer Bremse gegen das reihenweise Abgreifen.
 *
 * Die Verknüpfungs-Id ist #[Locked]: Livewire signiert sie, der Browser kann
 * sie nicht gegen eine fremde austauschen. So kommt über dieses Feld nur
 * heraus, was ohnehin schon auf der Seite stand.
 */
class KennwortFeld extends Component
{
    #[Locked]
    public int $linkId;

    public string $width = 'w-full';

    public bool $offen = false;

    /** Erst nach dem Klick gefüllt - vorher liegt kein Wert im Component. */
    public ?string $wert = null;

    public function mount(int $linkId, string $width = 'w-full'): void
    {
        $this->linkId = $linkId;
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
     * Kopiert das Kennwort, ohne es aufzudecken: Der Wert geht einmalig an den
     * Browser (in die Zwischenablage) und steht danach nirgends im DOM. So lässt
     * er sich übernehmen, ohne dass er auf dem Schirm erscheint.
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
     * Holt den Klartext - mit Rechteprüfung, Bremse und Protokolleintrag, siehe
     * App\Support\Kennwortabruf. Gemeinsamer Kern von zeigen() und kopieren().
     */
    private function wertHolen(): ?string
    {
        $link = CredentialLink::find($this->linkId);

        if (! $link) {
            return null;
        }

        try {
            return app(Kennwortabruf::class)->ausVerknuepfung($link, auth()->user());
        } catch (ZuVieleKennwortabrufe $bremse) {
            $this->addError('kennwort', $bremse->getMessage());

            return null;
        }
    }

    public function verbergen(): void
    {
        $this->wert = null;
        $this->offen = false;
    }

    public function render()
    {
        return view('livewire.kennwort-feld');
    }
}
