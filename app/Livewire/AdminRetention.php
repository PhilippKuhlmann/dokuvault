<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * How far ahead the warning comes, and how long a PDF export stays.
 *
 * Without a save button, as under "Allgemein": whoever enters a number has
 * entered it.
 *
 * The numbers used to stand in seven places in the code - twice the same one
 * with a comment saying it was the same. Whoever had changed one of them
 * would have left the others standing.
 */
class AdminRetention extends Component
{
    public int $vertraege = 0;

    public int $garantie = 0;

    public int $eol = 0;

    public int $pdfStunden = 0;

    public int $statistikApiTage = 0;

    public int $statistikMonate = 0;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->vertraege = Setting::contractDeadline();
        $this->garantie = Setting::warrantyDeadline();
        $this->eol = Setting::eolDeadline();
        $this->pdfStunden = Setting::pdfStunden();
        $this->statistikApiTage = Setting::statistikApiTage();
        $this->statistikMonate = Setting::statistikMonate();
    }

    public function updatedVertraege(): void
    {
        $this->save('vertraege', Setting::FRIST_VERTRAEGE, __('Lizenzen, Zertifikate und Domains'));
    }

    public function updatedGarantie(): void
    {
        $this->save('garantie', Setting::FRIST_GARANTIE, __('Garantien'));
    }

    public function updatedEol(): void
    {
        $this->save('eol', Setting::FRIST_EOL, __('Support-Ende'));
    }

    public function updatedPdfStunden(): void
    {
        // One hour at the bottom: the cleanup runs hourly, and a shorter
        // deadline would be a number without effect. One year at the top -
        // longer than a few days is not meant anyway.
        $this->check('pdfStunden', 1, 8760, __('Aufbewahrung der PDF-Ausgaben'));

        Setting::setzen(Setting::PDF_STUNDEN, $this->pdfStunden);

        $this->dispatch('notice', text: __('Frist gespeichert.'));
    }

    public function updatedStatistikApiTage(): void
    {
        $this->clamped('statistikApiTage', Setting::STATISTIK_API_TAGE, __('API-Auslastung'));
    }

    public function updatedStatistikMonate(): void
    {
        $this->clamped('statistikMonate', Setting::STATISTIK_MONATE, __('Verläufe'));
    }

    /** A setting with its range from Setting::ZAHLEN. */
    private function clamped(string $feld, string $schluessel, string $bezeichnung): void
    {
        [, $min, $max] = Setting::ZAHLEN[$schluessel];
        $this->check($feld, $min, $max, $bezeichnung);

        Setting::setzen($schluessel, $this->$feld);

        $this->dispatch('notice', text: __('Frist gespeichert.'));
    }

    /**
     * Check a deadline in days and store it.
     *
     * At least one day: a warning time of zero days is no deadline but a
     * switched-off warning - and one does not switch that off by accident
     * through an emptied field.
     *
     * At most five years. That is not a recommendation but a typo catcher:
     * whoever means 60 and types 600 warns about everything from then on. The
     * value itself belongs to the house - an operating system's end of life is
     * planned further ahead than a domain renewal.
     */
    private function save(string $feld, string $schluessel, string $bezeichnung): void
    {
        $this->check($feld, 1, 1825, $bezeichnung);

        Setting::setzen($schluessel, $this->$feld);

        $this->dispatch('notice', text: __('Frist gespeichert.'));
    }

    private function check(string $feld, int $min, int $max, string $bezeichnung): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            [$feld => ['required', 'integer', 'min:'.$min, 'max:'.$max]],
            [],
            [$feld => $bezeichnung]
        );
    }

    public function render()
    {
        return view('livewire.admin-retention')->layout('layouts.admin.app');
    }
}
