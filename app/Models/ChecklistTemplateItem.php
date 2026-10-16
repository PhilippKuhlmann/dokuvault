<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A step of a template - standard (customer_id null) or a customer's own. */
class ChecklistTemplateItem extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function template()
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function isStandard(): bool
    {
        return $this->customer_id === null;
    }
}
