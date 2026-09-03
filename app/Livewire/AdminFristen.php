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
class AdminFristen extends Component
{
    public int $vertraege = 0;

    public int $garantie = 0;

    public int $eol = 0;

    public int $pdfStunden = 0;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->vertraege = Setting::fristVertraege();
        $this->garantie = Setting::fristGarantie();
        $this->eol = Setting::fristEol();
        $this->pdfStunden = Setting::pdfStunden();
    }

    public function updatedVertraege(): void
    {
        $this->speichern('vertraege', Setting::FRIST_VERTRAEGE, __('Lizenzen, Zertifikate und Domains'));
    }

    public function updatedGarantie(): void
    {
        $this->speichern('garantie', Setting::FRIST_GARANTIE, __('Garantien'));
    }

    public function updatedEol(): void
    {
        $this->speichern('eol', Setting::FRIST_EOL, __('Support-Ende'));
    }

    public function updatedPdfStunden(): void
    {
        // Nach unten auf eine Stunde: Aufgeraeumt wird stuendlich, eine
        // kuerzere Frist waere eine Zahl ohne Wirkung. Nach oben ein Jahr -
        // laenger als ein paar Tage ist ohnehin nicht gemeint.
        $this->pruefen('pdfStunden', 1, 8760, __('Aufbewahrung der PDF-Ausgaben'));

        Setting::setzen(Setting::PDF_STUNDEN, $this->pdfStunden);

        $this->dispatch('hinweis', text: __('Frist gespeichert.'));
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
    private function speichern(string $feld, string $schluessel, string $bezeichnung): void
    {
        $this->pruefen($feld, 1, 1825, $bezeichnung);

        Setting::setzen($schluessel, $this->$feld);

        $this->dispatch('hinweis', text: __('Frist gespeichert.'));
    }

    private function pruefen(string $feld, int $min, int $max, string $bezeichnung): void
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
        return view('livewire.admin-fristen')->layout('layouts.admin.app');
    }
}
