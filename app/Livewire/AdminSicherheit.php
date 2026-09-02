<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Wie ein Kennwort aussehen muss, mit dem sich ein Benutzer anmeldet.
 *
 * Ohne Speichern-Knopf, wie unter "Allgemein": Wer ein Haekchen setzt, hat es
 * gesetzt. Ein Formular mit Knopf laesst offen, ob die letzte Aenderung noch
 * drin war.
 *
 * Nicht gemeint sind die dokumentierten Kennwoerter der Kunden - das Kennwort
 * eines Servers, eines Druckers, eines WLANs. Dort wird festgehalten, was ist,
 * nicht was sein soll: Ein Kunde mit einem schwachen Kennwort muss
 * dokumentierbar bleiben.
 */
class AdminSicherheit extends Component
{
    /** Mindestlaenge; acht ist Laravels Vorgabe und galt bisher stillschweigend. */
    public int $pwMin = 8;

    public bool $pwMixed = false;

    public bool $pwNumbers = false;

    public bool $pwSymbols = false;

    public bool $pwUncompromised = false;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->pwMin = Setting::kennwortMindestlaenge();
        $this->pwMixed = (bool) Setting::wert(Setting::PW_MIXED, false);
        $this->pwNumbers = (bool) Setting::wert(Setting::PW_NUMBERS, false);
        $this->pwSymbols = (bool) Setting::wert(Setting::PW_SYMBOLS, false);
        $this->pwUncompromised = (bool) Setting::wert(Setting::PW_UNCOMPROMISED, false);
    }

    public function updatedPwMin(): void
    {
        Gate::authorize('admin_setting');

        // Nach oben begrenzt, weil bcrypt bei 72 Byte abschneidet - eine
        // Mindestlaenge darueber waere eine Zahl ohne Wirkung.
        $this->validate(
            ['pwMin' => ['required', 'integer', 'min:8', 'max:64']],
            [],
            ['pwMin' => __('Mindestlänge')]
        );

        Setting::setzen(Setting::PW_MIN, $this->pwMin);

        $this->dispatch('hinweis', text: __('Kennwortregel gespeichert.'));
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

    /** Null statt false: "nicht gesetzt" und "abgewaehlt" sind hier dasselbe. */
    private function haken(string $schluessel, bool $wert): void
    {
        Gate::authorize('admin_setting');

        Setting::setzen($schluessel, $wert ? 1 : null);

        $this->dispatch('hinweis', text: __('Kennwortregel gespeichert.'));
    }

    public function render()
    {
        return view('livewire.admin-sicherheit', [
            // Derselbe Satz, den die Benutzer unter ihrem Kennwortfeld sehen -
            // damit hier steht, was die Haekchen bewirken.
            'hinweis' => Setting::kennwortHinweis(),
        ])->layout('layouts.admin.app');
    }
}
