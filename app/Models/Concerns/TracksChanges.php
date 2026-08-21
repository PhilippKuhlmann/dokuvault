<?php

namespace App\Models\Concerns;

use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
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
            ->causedBy(auth()->user())
            // Der Name wird mitgeschrieben, nicht nachgeladen: Ein Eintrag
            // überlebt sein Objekt, und ein Verweis auf eine entfernte Klasse
            // bricht beim Auflösen die ganze Protokollseite.
            ->withProperties([
                'felder' => $geaendert,
                'objekt' => $this->name ?? $this->username ?? null,
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
        $tage = Setting::passwortHistorieTage();

        // Abgeschaltet heisst abgeschaltet: Dann entsteht gar kein Eintrag,
        // nicht einer, der spaeter geloescht wird.
        if ($tage < 1) {
            return null;
        }

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
            'subject_name' => $this->name ?? $this->username ?? null,
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
