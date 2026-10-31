<?php

namespace App\Models\Concerns;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Throwable;

/**
 * Turns on the activity log (who changed what, when) for a model.
 *
 * Two things are kept apart here that easily fall together:
 *
 * 1. The VALUE of a password must never reach the log. spatie/activitylog
 *    reads the values through the Eloquent accessors, so an encrypted field
 *    would otherwise stand here in plain text - old value and new.
 * 2. The FACT that a password was changed does belong in there. That is
 *    exactly what one searches the log for.
 */
trait TracksChanges
{
    use LogsActivity;

    public static function bootTracksChanges(): void
    {
        static::updated(fn (Model $model) => $model->logPasswordChange());
    }

    /**
     * The previous passwords of this object, newest first.
     */
    public function passwordHistory(): MorphMany
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
                // Written by every daily check (domains:check) - a log entry
                // a day saying "checked again" would bury the real changes.
                'checked_at',
                'check_error',
                'agent_reported_at',
                // What an agent reports is the machine's state, replaced by
                // every run: used storage and a guest's power state change
                // all the time and would bury the real changes.
                'agent_details',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Write the name of the object into every entry.
     *
     * logOnlyDirty stores only the changed fields. Change nothing but the
     * registrar on a domain and you leave an entry that holds no name - the
     * log then read "Domain #1". For working out who did what, that is no
     * use.
     *
     * Written along instead of loaded on display: an activity entry outlives
     * its object, and a reference to a removed class breaks the whole page
     * when resolved.
     */
    public function tapActivity(Activity $aktivitaet, string $ereignis): void
    {
        $name = $this->activityName();

        if (blank($name)) {
            return;
        }

        $aktivitaet->properties = $aktivitaet->properties->put('objekt', $name);
    }

    /**
     * What makes this object recognisable in the log.
     *
     * Overridable: links such as CredentialLink or RackItem have no field of
     * their own that says anything - they carry the name of what they
     * connect. Without that, more than half of all rows held nothing but a
     * number.
     */
    public function activityName(): ?string
    {
        foreach (config('custom.name_fields') as $feld) {
            if (filled($this->{$feld} ?? null)) {
                return (string) $this->{$feld};
            }
        }

        return null;
    }

    /**
     * Write an entry of its own when a password has changed.
     *
     * An entry of its own and not an addition to the change entry: if only
     * the password changes, dontSubmitEmptyLogs means no change entry comes
     * into being for anything to hang on - and that is the most common case.
     */
    protected function logPasswordChange(): void
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

            if ($this->onlyReEncrypted($spalte)) {
                continue;
            }

            $geaendert[] = $spalte;

            if ($id = $this->keepOldPassword($spalte)) {
                $verlaufIds[] = $id;
            }
        }

        if ($geaendert === []) {
            return;
        }

        activity()
            ->performedOn($this)
            // Not auth()->user(): an agent has no signed-in user, so the entry
            // would stand there without a causer. The resolver returns the
            // signed-in user and, where the agent middleware set a token,
            // that token.
            ->causedBy(CauserResolver::resolve())
            // The name is written along, not loaded later: an entry outlives
            // its object, and a reference to a removed class breaks the whole
            // activity page when resolved.
            ->withProperties([
                'felder' => $geaendert,
                'objekt' => $this->activityName(),
                // References, not values: the log shows the previous password
                // but fetches it from the history on display. That keeps it out
                // of the activity entry, which stays forever, and lets it go
                // with the configured deadline.
                'verlauf_ids' => $verlaufIds,
            ])
            ->event('password_changed')
            ->log('Kennwort geändert');
    }

    /**
     * Keep the previous password so it can be looked up.
     *
     * The case this is about: someone changes a password wrongly and the old
     * one is needed back. It sits encrypted in a table of its own - not in
     * the log, which stays forever and lists every customer on one page.
     *
     *
     * @return int|null Id of the entry, so the activity entry can point at
     *                  it
     */
    protected function keepOldPassword(string $spalte): ?int
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

        // An account password is a bcrypt hash: useless for getting the old
        // password back, but crackable offline by whoever may read the log.
        // The change itself is still logged.
        if ($this instanceof User) {
            return null;
        }

        return PasswordHistory::create([
            'customer_id' => $this->customer_id ?? null,
            'subject_type' => $this::class,
            'subject_id' => $this->getKey(),
            // activityName() instead of name/username: a WLAN is called 'ssid',
            // an extension 'extension', an address 'address'. For those there
            // was nothing here before - and a kept password without a note of
            // what it belonged to is worthless exactly when it is needed:
            // when the device is long gone.
            'subject_name' => $this->activityName(),
            'field' => $spalte,
            'value' => $alt,
            'user_id' => auth()->id(),
        ])->id;
    }

    /**
     * Gleicher Klartext, neuer Chiffretext?
     *
     * Crypt::encryptString produces a different result on every call. Without
     * this comparison every save of the form reported a password change -
     * even when nobody touched the field, because the form sends the
     * unchanged value along.
     */
    protected function onlyReEncrypted(string $spalte): bool
    {
        try {
            return $this->getOriginal($spalte) === $this->{$spalte};
        } catch (Throwable) {
            // If the old value cannot be decrypted, the change is genuine in
            // case of doubt. A missing activity entry would be the more
            // expensive mistake.
            return false;
        }
    }
}
