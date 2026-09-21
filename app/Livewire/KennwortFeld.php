<?php

namespace App\Livewire;

use App\Models\CredentialLink;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $link = CredentialLink::with('login')->find($this->linkId);

        if (! $link || ! $link->login) {
            return;
        }

        // Dasselbe Recht wie die Liste, auf der das Feld steht: Kennwort und
        // Schlüssel liegen in derselben Tabelle, haben aber getrennte Rechte.
        Gate::authorize($link->login->istSchluessel() ? 'sshkey_viewAny' : 'logingeneral_viewAny');

        // Bremse gegen das reihenweise Abgreifen: Eine Handvoll Kennwörter
        // nachzusehen ist normal, hundert in einer Minute nicht.
        $schluessel = 'kennwort-ansehen:'.auth()->id();

        if (RateLimiter::tooManyAttempts($schluessel, config('custom.kennwort.ansehen_je_minute'))) {
            $this->addError('kennwort', __('Zu viele Kennwortabrufe in kurzer Zeit. Bitte einen Moment warten.'));

            return;
        }

        RateLimiter::hit($schluessel, 60);

        // Die Tatsache der Einsicht gehört ins Protokoll, der Wert nie. Deshalb
        // der activity()-Helfer statt der Model-Ereignisse: Er schreibt genau
        // das Ereignis, das wir angeben, und keine Attribute.
        activity()
            ->event('kennwort_angesehen')
            ->performedOn($link->login)
            ->causedBy(auth()->user())
            ->withProperties([
                'objekt' => $link->zielBezeichnung(),
                'attributes' => array_filter([
                    'Zugang' => $link->note ?: $link->login->name,
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('Kennwort angesehen');

        $this->wert = $link->login->password;
        $this->offen = true;
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
