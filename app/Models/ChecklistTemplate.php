<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The standard steps of an on- or offboarding. There is one template per
 * type; customers adjust it through their own and hidden items.
 */
class ChecklistTemplate extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public static function ofType(string $type): self
    {
        return static::where('type', $type)->firstOrFail();
    }

    public function items()
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * The steps that apply to one customer: standard items it has not
     * switched off, plus its own.
     */
    public function itemsFor(Customer $customer)
    {
        $hidden = $customer->hiddenChecklistItems()->pluck('checklist_template_items.id');

        return $this->items()
            ->where(fn ($query) => $query->whereNull('customer_id')->orWhere('customer_id', $customer->id))
            ->whereNotIn('id', $hidden)
            ->get();
    }
}
