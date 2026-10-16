<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A program the customer can tick in the onboarding form. Maintained per
 * customer by IT. Hardware is left out for now; 'kind' stays so it can return. Network drives come from the
 * documentation instead (NetworkShare::in_onboarding_form).
 */
class ChecklistOption extends Model
{
    public const KINDS = ['app'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'needs_approval' => 'boolean',
        'active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** The documented AD group the checklist names for this program. */
    public function adGroup()
    {
        return $this->belongsTo(ADGroup::class, 'ad_group_id');
    }

    /** Active and of an offered kind - leftovers of a dropped kind stay out. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->whereIn('kind', self::KINDS);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }
}
