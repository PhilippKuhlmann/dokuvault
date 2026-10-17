<?php

namespace App\Support;

use App\Exceptions\ZuVieleKennwortabrufe;
use App\Models\CredentialLink;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Der einzige Weg, auf dem ein Kennwort im Klartext den Server verlaesst.
 *
 * Stand vorher zweimal in den Livewire-Feldern (KennwortFeld, GeheimFeld). Die
 * App braucht denselben Weg - und eine dritte Kopie haette irgendwann eine
 * Pruefung weniger als die anderen beiden. Jetzt gilt fuer Browser und App
 * dasselbe: Recht wie die Liste, eine gemeinsame Bremse je Benutzer, ein
 * Eintrag im Protokoll.
 *
 * Neu gegenueber den Feldern ist die Pruefung der Kundenzugehoerigkeit. Im
 * Browser waren die Ids von Livewire gesperrt und kamen nur von einer Seite,
 * die schon nach Kunde gefiltert war. Die App schickt die Id selbst.
 */
class Kennwortabruf
{
    /** Das Kennwort hinter einer Verknuepfung an einem Geraet. */
    public function ausVerknuepfung(CredentialLink $link, User $nutzer): ?string
    {
        $link->loadMissing('login');

        if (! $link->login) {
            return null;
        }

        // Dasselbe Recht wie die Liste, auf der das Feld steht: Kennwort und
        // Schlüssel liegen in derselben Tabelle, haben aber getrennte Rechte.
        Gate::forUser($nutzer)->authorize($link->login->istSchluessel() ? 'sshkey_viewAny' : 'logingeneral_viewAny');

        $this->nurEigenerKunde($nutzer, $link->customer_id);

        $this->bremsen($nutzer);

        // Die Tatsache der Einsicht gehört ins Protokoll, der Wert nie. Deshalb
        // der activity()-Helfer statt der Model-Ereignisse: Er schreibt genau
        // das Ereignis, das wir angeben, und keine Attribute.
        activity()
            ->event('kennwort_angesehen')
            ->performedOn($link->login)
            ->causedBy($nutzer)
            ->withProperties([
                'objekt' => $link->zielBezeichnung(),
                'attributes' => array_filter([
                    'Zugang' => $link->note ?: $link->login->name,
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('Kennwort angesehen');

        return $link->login->password;
    }

    /**
     * Ein Geheimnis, das direkt am Objekt haengt (BMC-Passwort, DSRM-Passwort,
     * Verschluesselungscode ...).
     *
     * @param  class-string  $modell
     */
    public function ausFeld(string $modell, int $id, string $feld, User $nutzer): ?string
    {
        // Feld-Whitelist: Nur echte Geheimnis-Spalten, nie ein beliebiges
        // Attribut. Modell muss ein Eloquent-Model sein.
        if (! in_array($feld, config('custom.secret_columns'), true)
            || ! is_subclass_of($modell, Model::class)) {
            return null;
        }

        // Dasselbe Recht wie die Liste/Karte, auf der das Feld steht.
        Gate::forUser($nutzer)->authorize('viewAny', $modell);

        $this->bremsen($nutzer);

        $objekt = $modell::find($id);

        if (! $objekt) {
            return null;
        }

        $this->nurEigenerKunde($nutzer, $objekt->customer_id);

        activity()
            ->event('kennwort_angesehen')
            ->performedOn($objekt)
            ->causedBy($nutzer)
            ->withProperties([
                'objekt' => $objekt->name ?? $objekt->host ?? (class_basename($objekt).' #'.$objekt->id),
                'attributes' => array_filter([
                    'Feld' => config('custom.secret_field_labels')[$feld] ?? $feld,
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('Kennwort angesehen');

        return $objekt->{$feld};
    }

    /**
     * Bremse gegen das reihenweise Abgreifen: Eine Handvoll Kennwörter
     * nachzusehen ist normal, hundert in einer Minute nicht. Ein gemeinsames
     * Budget je Benutzer - Browser und App zaehlen zusammen.
     */
    private function bremsen(User $nutzer): void
    {
        $schluessel = 'kennwort-ansehen:'.$nutzer->id;

        if (RateLimiter::tooManyAttempts($schluessel, Setting::kennwortAbrufe())) {
            throw new ZuVieleKennwortabrufe(RateLimiter::availableIn($schluessel));
        }

        RateLimiter::hit($schluessel, 60);
    }

    private function nurEigenerKunde(User $nutzer, $kundeId): void
    {
        abort_if($nutzer->hasCustomer() && (int) $nutzer->customer_id !== (int) $kundeId, 403);
    }
}
