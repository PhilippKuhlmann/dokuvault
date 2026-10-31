<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Wie lange vorher gewarnt wird, und wie lange eine PDF-Ausgabe liegen bleibt.
 *
 * Ohne Speichern-Knopf, wie unter "Allgemein": Wer eine Zahl eintraegt, hat sie
 * eingetragen.
 *
 * Die Zahlen standen vorher an sieben Stellen einzeln im Code - zweimal
 * dieselbe mit dem Kommentar, es sei dieselbe. Wer eine davon geaendert haette,
 * haette die uebrigen stehen lassen.
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
        // Nach unten auf eine Stunde: Aufgeraeumt wird stuendlich, eine
        // kuerzere Frist waere eine Zahl ohne Wirkung. Nach oben ein Jahr -
        // laenger als ein paar Tage ist ohnehin nicht gemeint.
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
     * Eine Frist in Tagen pruefen und ablegen.
     *
     * Mindestens ein Tag: Eine Vorwarnzeit von null Tagen ist keine Frist,
     * sondern eine abgeschaltete Warnung - und die schaltet man nicht
     * versehentlich ueber ein leergeraeumtes Feld ab.
     *
     * Hoechstens fuenf Jahre. Das ist keine Empfehlung, sondern ein
     * Tippfehlerfang: Wer 60 meint und 600 tippt, warnt ab sofort vor allem.
     * Der Wert selbst gehoert dem Haus - eine Betriebssystem-Ablauefe plant
     * man laenger im Voraus als eine Domainverlaengerung.
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
