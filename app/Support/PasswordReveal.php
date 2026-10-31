<?php

namespace App\Support;

use App\Exceptions\TooManyPasswordReveals;
use App\Models\CredentialLink;
use App\Models\PasswordHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The only way a password leaves the server in plain text.
 *
 * This used to sit twice in the Livewire fields (PasswordField,
 * SecretField). The app needs the same path - and a third copy would
 * sooner or later have one check fewer than the other two. Browser and
 * app now share the same rules: the right of the list it sits on, one
 * shared throttle per user, one entry in the activity log.
 *
 * New compared with the fields is the customer check. In the browser
 * the ids were locked by Livewire and came from a page already filtered
 * by customer. The app sends the id itself.
 */
class PasswordReveal
{
    /** The password behind a credential link on a device. */
    public function ausVerknuepfung(CredentialLink $link, User $nutzer): ?string
    {
        $link->loadMissing('login');

        if (! $link->login) {
            return null;
        }

        // The same right as the list the field sits on: password and key
        // live in the same table but have separate permissions.
        Gate::forUser($nutzer)->authorize($link->login->istSchluessel() ? 'sshkey_viewAny' : 'logingeneral_viewAny');

        $this->onlyOwnCustomer($nutzer, $link->customer_id);

        $this->bremsen($nutzer);

        // The fact of the reveal belongs in the log, the value never.
        // Hence the activity() helper instead of the model events: it
        // writes exactly the event we name, and no attributes.
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
     * A secret that hangs directly on the object (BMC password, DSRM
     * password, encryption key ...).
     *
     * @param  class-string  $modell
     */
    public function fromField(string $modell, int $id, string $feld, User $nutzer): ?string
    {
        // Field allowlist: only real secret columns, never an arbitrary
        // attribute. The model must be an Eloquent model.
        if (! in_array($feld, config('custom.secret_columns'), true)
            || ! is_subclass_of($modell, Model::class)) {
            return null;
        }

        // The same right as the list or card the field sits on.
        Gate::forUser($nutzer)->authorize('viewAny', $modell);

        $this->bremsen($nutzer);

        $objekt = $modell::find($id);

        if (! $objekt) {
            return null;
        }

        $this->onlyOwnCustomer($nutzer, $objekt->customer_id);

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
     * A previous password from the history, shown in the activity log.
     *
     * Was a way of its own before: no brake, no log entry, no customer check -
     * a customer-bound log viewer read old passwords of every customer in
     * bulk. Now the same rules as every other reveal, plus the right to see
     * the kind of object the password belonged to.
     */
    public function fromHistory(PasswordHistory $eintrag, User $nutzer): ?string
    {
        // Account passwords are hashes, never shown - see TracksChanges.
        if ($eintrag->subject_type === User::class || ! is_subclass_of($eintrag->subject_type, Model::class)) {
            return null;
        }

        Gate::forUser($nutzer)->authorize('admin_activity');
        Gate::forUser($nutzer)->authorize('viewAny', $eintrag->subject_type);
        $this->onlyOwnCustomer($nutzer, $eintrag->customer_id);

        $this->bremsen($nutzer);

        activity()
            ->event('kennwort_angesehen')
            ->performedOn($eintrag->subject_type::withoutGlobalScopes()->find($eintrag->subject_id) ?? $eintrag)
            ->causedBy($nutzer)
            ->withProperties([
                'objekt' => $eintrag->subject_name,
                'attributes' => array_filter([
                    'Feld' => (config('custom.secret_field_labels')[$eintrag->field] ?? $eintrag->field).' (bisheriges)',
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('Kennwort angesehen');

        return $eintrag->value;
    }

    /**
     * A brake against harvesting in bulk: looking up a handful of passwords
     * is normal, a hundred in a minute is not. One shared budget per user -
     * browser and app count together.
     */
    private function bremsen(User $nutzer): void
    {
        $schluessel = 'kennwort-ansehen:'.$nutzer->id;

        if (RateLimiter::tooManyAttempts($schluessel, Setting::kennwortAbrufe())) {
            throw new TooManyPasswordReveals(RateLimiter::availableIn($schluessel));
        }

        RateLimiter::hit($schluessel, 60);
    }

    private function onlyOwnCustomer(User $nutzer, $kundeId): void
    {
        abort_if($nutzer->hasCustomer() && (int) $nutzer->customer_id !== (int) $kundeId, 403);
    }
}
