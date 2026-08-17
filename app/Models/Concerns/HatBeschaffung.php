<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Beschaffung und Garantie eines Geraets.
 *
 * Der Trait haelt zusammen, was sonst siebzehnmal einzeln in den Models stuende:
 * die Datums-Casts und die Frage, ob die Garantie demnaechst ablaeuft. Er ist
 * gleichzeitig das Erkennungsmerkmal - Customer::ablaufendeGarantien() sucht
 * ueber class_uses_recursive genau die Models, die ihn einbinden. Damit ist ein
 * neuer Geraetetyp mit einer Zeile dabei, ohne dass eine zweite Liste gepflegt
 * werden muss.
 */
trait HatBeschaffung
{
    /**
     * Laravel ruft initialize<TraitName> im Konstruktor auf. mergeCasts statt
     * eines $casts-Arrays im Model: Die meisten Geraete haben schon eigene
     * Casts, die hier nicht ueberschrieben werden duerfen.
     */
    public function initializeHatBeschaffung(): void
    {
        $this->mergeCasts([
            'purchase_date' => 'date',
            'warranty_until' => 'date',
            'eol_date' => 'date',
        ]);
    }

    /**
     * Geraete, deren Garantie in den naechsten $tage Tagen ablaeuft oder schon
     * abgelaufen ist.
     */
    public function scopeGarantieLaeuftAb(Builder $query, int $tage = 60): Builder
    {
        return $query->whereNotNull('warranty_until')
            ->whereDate('warranty_until', '<=', now()->addDays($tage));
    }

    /**
     * Tage bis zum Garantieende - negativ, wenn sie schon abgelaufen ist.
     * Null, wenn kein Datum erfasst wurde.
     */
    public function garantieTage(): ?int
    {
        if (! $this->warranty_until) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->warranty_until->startOfDay(), false);
    }

    public function garantieAbgelaufen(): bool
    {
        return ($this->garantieTage() ?? 1) < 0;
    }
}
