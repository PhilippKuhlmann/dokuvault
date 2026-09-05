<?php

namespace App\Models\Concerns;

use App\Models\PasswordHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Throwable;

/**
 * Aktiviert das Aktivitätsprotokoll (wer hat wann was geändert) für ein Model.
 *
 * Zwei Dinge werden hier auseinandergehalten, die leicht zusammenfallen:
 *
 * 1. Der WERT eines Kennworts darf nie ins Protokoll. spatie/activitylog liest
 *    die Werte über die Eloquent-Accessoren, ein verschlüsseltes Feld stünde
 *    hier sonst im Klartext - und zwar alt und neu.
 * 2. Die TATSACHE, dass ein Kennwort geändert wurde, gehört sehr wohl hinein.
 *    Genau danach sucht man im Protokoll.
 */
trait TracksChanges
{
    use LogsActivity;

    public static function bootTracksChanges(): void
    {
        static::updated(fn (Model $model) => $model->protokolliereKennwortaenderung());
    }

    /**
     * Die bisherigen Kennwoerter dieses Objekts, neueste zuerst.
     */
    public function kennwortVerlauf(): MorphMany
    {
        return $this->morphMany(PasswordHistory::class, 'subject')->latest('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([
                ...config('custom.secret_columns'),
                'created_at',
                'updated_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Den Namen des Objekts in jeden Eintrag schreiben.
     *
     * logOnlyDirty speichert nur die geänderten Felder. Wer an einer Domain
     * bloß den Registrar ändert, hinterlässt damit einen Eintrag, in dem kein
     * Name steht - im Protokoll stand dann "Domain #1". Zum Nachvollziehen,
     * wer was angestellt hat, taugt das nicht.
     *
     * Mitgeschrieben statt beim Anzeigen nachgeladen: Ein Protokolleintrag
     * überlebt sein Objekt, und ein Verweis auf eine entfernte Klasse bricht
     * beim Auflösen die ganze Seite.
     */
    public function tapActivity(Activity $aktivitaet, string $ereignis): void
    {
        $name = $this->protokollName();

        if (blank($name)) {
            return;
        }

        $aktivitaet->properties = $aktivitaet->properties->put('objekt', $name);
    }

    /**
     * Woran man dieses Objekt im Protokoll erkennt.
     *
     * Ueberschreibbar: Verknuepfungen wie CredentialLink oder RackItem haben
     * kein eigenes Feld, das etwas aussagt - sie tragen den Namen dessen, was
     * sie verbinden. Ohne das stand in mehr als der Haelfte aller Zeilen nur
     * eine Nummer.
     */
    public function protokollName(): ?string
    {
        foreach (config('custom.name_fields') as $feld) {
            if (filled($this->{$feld} ?? null)) {
                return (string) $this->{$feld};
            }
        }

        return null;
    }

    /**
     * Einen eigenen Eintrag schreiben, wenn sich ein Kennwort geändert hat.
     *
     * Ein eigener Eintrag und nicht ein Zusatz am Änderungs-Eintrag: Wird nur
     * das Kennwort geändert, entsteht wegen dontSubmitEmptyLogs gar kein
     * Änderungs-Eintrag, an den sich etwas hängen liesse - und genau dieser
     * Fall ist der häufigste.
     */
    protected function protokolliereKennwortaenderung(): void
    {
        $geaendert = [];
        $verlaufIds = [];

        foreach (config('custom.secret_columns') as $spalte) {
            if (in_array($spalte, config('custom.non_password_secrets'), true)) {
                continue;
            }

            if (! array_key_exists($spalte, $this->getAttributes()) || ! $this->wasChanged($spalte)) {
                continue;
            }

            if ($this->nurNeuVerschluesselt($spalte)) {
                continue;
            }

            $geaendert[] = $spalte;

            if ($id = $this->altesKennwortSichern($spalte)) {
                $verlaufIds[] = $id;
            }
        }

        if ($geaendert === []) {
            return;
        }

        activity()
            ->performedOn($this)
            // Nicht auth()->user(): Ein Agent hat keinen angemeldeten
            // Benutzer, der Eintrag stuende sonst ohne Verursacher da. Der
            // Resolver liefert den angemeldeten Benutzer und, wo die
            // Agent-Middleware einen Token gesetzt hat, diesen.
            ->causedBy(CauserResolver::resolve())
            // Der Name wird mitgeschrieben, nicht nachgeladen: Ein Eintrag
            // überlebt sein Objekt, und ein Verweis auf eine entfernte Klasse
            // bricht beim Auflösen die ganze Protokollseite.
            ->withProperties([
                'felder' => $geaendert,
                'objekt' => $this->protokollName(),
                // Verweise, keine Werte: Das Protokoll zeigt das bisherige
                // Kennwort, holt es aber beim Anzeigen aus der Historie. Damit
                // steht es nicht im Protokolleintrag, der ewig bleibt, und
                // verschwindet mit der eingestellten Frist.
                'verlauf_ids' => $verlaufIds,
            ])
            ->event('password_changed')
            ->log('Kennwort geändert');
    }

    /**
     * Das bisherige Kennwort aufheben, damit es sich nachschlagen laesst.
     *
     * Der Fall, um den es geht: Jemand aendert ein Kennwort falsch, und man
     * braucht das alte zurueck. Es steht verschluesselt in einer eigenen
     * Tabelle - nicht im Protokoll, das ewig bleibt und alle Kunden auf einer
     * Seite listet.
     *
     * @return int|null Id des Eintrags, damit der Protokolleintrag darauf
     *                  verweisen kann
     */
    protected function altesKennwortSichern(string $spalte): ?int
    {
        try {
            $alt = $this->getOriginal($spalte);
        } catch (Throwable) {
            return null;
        }

        // Beim ersten Setzen gibt es nichts aufzuheben.
        if (blank($alt)) {
            return null;
        }

        return PasswordHistory::create([
            'customer_id' => $this->customer_id ?? null,
            'subject_type' => $this::class,
            'subject_id' => $this->getKey(),
            // protokollName() statt name/username: Ein WLAN heisst 'ssid', ein
            // Anschluss 'extension', eine Adresse 'address'. Fuer die stand
            // hier bisher nichts - und ein aufgehobenes Kennwort ohne Angabe,
            // wozu es gehoerte, ist genau dann wertlos, wenn man es braucht:
            // wenn das Geraet laengst weg ist.
            'subject_name' => $this->protokollName(),
            'field' => $spalte,
            'value' => $alt,
            'user_id' => auth()->id(),
        ])->id;
    }

    /**
     * Gleicher Klartext, neuer Chiffretext?
     *
     * Crypt::encryptString erzeugt bei jedem Aufruf ein anderes Ergebnis. Ohne
     * diesen Vergleich meldete jedes Speichern des Formulars eine
     * Kennwortänderung - auch wenn niemand das Feld angefasst hat, denn das
     * Formular schickt den unveränderten Wert mit.
     */
    protected function nurNeuVerschluesselt(string $spalte): bool
    {
        try {
            return $this->getOriginal($spalte) === $this->{$spalte};
        } catch (Throwable) {
            // Lässt sich der alte Wert nicht entschlüsseln, ist die Änderung im
            // Zweifel echt. Ein fehlender Protokolleintrag wäre der teurere
            // Fehler.
            return false;
        }
    }
}
