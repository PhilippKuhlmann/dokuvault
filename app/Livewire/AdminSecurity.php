<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * What a password has to look like for a user to sign in with it.
 *
 * Without a save button, as under "Allgemein": whoever ticks a box has
 * ticked it. A form with a button leaves it open whether the last change made
 * it in.
 *
 * Not meant are the documented passwords of the customers - the password of a
 * server, a printer, a WLAN. There we record what is, not what ought to be: a
 * customer with a weak password has to stay documentable.
 * dokumentierbar bleiben.
 */
class AdminSecurity extends Component
{
    /** Minimum length; eight is Laravel's default and applied silently before. */
    public int $pwMin = 8;

    public bool $pwMixed = false;

    public bool $pwNumbers = false;

    public bool $pwSymbols = false;

    public bool $pwUncompromised = false;

    /** Throttle against guessing - the same numbers apply to the second factor. */
    public int $versuche = 0;

    public int $sperre = 0;

    public int $herkunft = 0;

    /** Sitzung. */
    public int $sitzungMinuten = 0;

    public bool $sitzungSchliessen = false;

    public int $rememberTage = 0;

    public int $kennwortAbrufe = 0;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->pwMin = Setting::passwordMinLength();
        $this->pwMixed = (bool) Setting::wert(Setting::PW_MIXED, false);
        $this->pwNumbers = (bool) Setting::wert(Setting::PW_NUMBERS, false);
        $this->pwSymbols = (bool) Setting::wert(Setting::PW_SYMBOLS, false);
        $this->pwUncompromised = (bool) Setting::wert(Setting::PW_UNCOMPROMISED, false);

        $this->versuche = Setting::loginAttempts();
        $this->sperre = Setting::loginLockoutMinutes();
        $this->herkunft = Setting::loginOrigin();

        $this->sitzungMinuten = Setting::sitzungMinuten();
        $this->sitzungSchliessen = Setting::sitzungBeimSchliessen();
        $this->rememberTage = Setting::rememberTage();
        $this->kennwortAbrufe = Setting::kennwortAbrufe();
    }

    public function updatedVersuche(): void
    {
        $this->zahl('versuche', Setting::ANMELDUNG_VERSUCHE, 1, 50, __('Fehlversuche je Konto'));
    }

    public function updatedSperre(): void
    {
        $this->zahl('sperre', Setting::ANMELDUNG_SPERRE, 1, 1440, __('Sperrdauer'));
    }

    public function updatedHerkunft(): void
    {
        $this->zahl('herkunft', Setting::ANMELDUNG_HERKUNFT, 1, 1000, __('Fehlversuche je Herkunft'));
    }

    public function updatedSitzungMinuten(): void
    {
        // Five minutes at the bottom: shorter and the application signs
        // somebody out while they are typing. 30 days at the top - longer is
        // Sitzung mehr, dafuer gibt es "Angemeldet bleiben".
        $this->zahl('sitzungMinuten', Setting::SITZUNG_MINUTEN, 5, 43200, __('Dauer einer Sitzung'));
    }

    public function updatedRememberTage(): void
    {
        $this->zahl('rememberTage', Setting::REMEMBER_TAGE, 1, 365, __('„Angemeldet bleiben“'));
    }

    public function updatedKennwortAbrufe(): void
    {
        [, $min, $max] = Setting::ZAHLEN[Setting::KENNWORT_ABRUFE];
        $this->zahl('kennwortAbrufe', Setting::KENNWORT_ABRUFE, $min, $max, __('Kennwortabrufe je Minute'));
    }

    public function updatedSitzungSchliessen(): void
    {
        $this->haken(Setting::SITZUNG_SCHLIESSEN, $this->sitzungSchliessen);
    }

    /** Check a number and store it. */
    private function zahl(string $feld, string $schluessel, int $min, int $max, string $bezeichnung): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            [$feld => ['required', 'integer', 'min:'.$min, 'max:'.$max]],
            [],
            [$feld => $bezeichnung]
        );

        Setting::setzen($schluessel, $this->$feld);

        $this->dispatch('notice', text: __('Einstellung gespeichert.'));
    }

    public function updatedPwMin(): void
    {
        Gate::authorize('admin_setting');

        // Capped at the top because bcrypt truncates at 72 bytes - a minimum
        // length above that would be a number without effect.
        $this->validate(
            ['pwMin' => ['required', 'integer', 'min:8', 'max:64']],
            [],
            ['pwMin' => __('Mindestlänge')]
        );

        Setting::setzen(Setting::PW_MIN, $this->pwMin);

        $this->dispatch('notice', text: __('Kennwortregel gespeichert.'));
    }

    public function updatedPwMixed(): void
    {
        $this->haken(Setting::PW_MIXED, $this->pwMixed);
    }

    public function updatedPwNumbers(): void
    {
        $this->haken(Setting::PW_NUMBERS, $this->pwNumbers);
    }

    public function updatedPwSymbols(): void
    {
        $this->haken(Setting::PW_SYMBOLS, $this->pwSymbols);
    }

    public function updatedPwUncompromised(): void
    {
        $this->haken(Setting::PW_UNCOMPROMISED, $this->pwUncompromised);
    }

    /** Null instead of false: "not set" and "unticked" are the same thing here. */
    private function haken(string $schluessel, bool $wert): void
    {
        Gate::authorize('admin_setting');

        Setting::setzen($schluessel, $wert ? 1 : null);

        $this->dispatch('notice', text: __('Kennwortregel gespeichert.'));
    }

    public function render()
    {
        return view('livewire.admin-security', [
            // The same sentence the users see under their password field - so
            // that what the boxes do stands here.
            'hinweis' => Setting::passwordNotice(),
        ])->layout('layouts.admin.app');
    }
}
