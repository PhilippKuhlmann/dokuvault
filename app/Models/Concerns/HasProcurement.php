<?php

namespace App\Models\Concerns;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

/**
 * Procurement and warranty of a device.
 *
 * The trait holds together what would otherwise stand seventeen times over in
 * the models: the date casts and the question whether the warranty runs out
 * soon. At the same time it is the marker - Customer::ablaufendeGarantien()
 * looks through class_uses_recursive for exactly the models that use it. A new
 * device kind is therefore included with one line, without a second list to
 * maintain.
 */
trait HasProcurement
{
    /**
     * Laravel calls initialize<TraitName> in the constructor. mergeCasts
     * instead of a $casts array in the model: most devices already have casts
     * of their own that must not be overwritten here.
     */
    public function initializeHasProcurement(): void
    {
        $this->mergeCasts([
            'purchase_date' => 'date',
            'warranty_until' => 'date',
            'eol_date' => 'date',
        ]);
    }

    /**
     * Devices whose warranty runs out in the next $tage days or has run out
     * already.
     */
    public function scopeGarantieLaeuftAb(Builder $query, ?int $tage = null): Builder
    {
        $tage ??= Setting::warrantyDeadline();

        return $query->whereNotNull('warranty_until')
            ->whereDate('warranty_until', '<=', now()->addDays($tage));
    }

    /**
     * Days until the end of the warranty - negative when it has passed
     * already. Null when no date was recorded.
     */
    public function warrantyDays(): ?int
    {
        if (! $this->warranty_until) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->warranty_until->startOfDay(), false);
    }

    public function warrantyExpired(): bool
    {
        return ($this->warrantyDays() ?? 1) < 0;
    }
}
