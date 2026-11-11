<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Model;

/** A step of a template - standard (customer_id null) or a customer's own. */
class ChecklistTemplateItem extends Model
{
    use BelongsToCustomer;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function template()
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    /** The standard steps (customer_id null) belong to every customer's template. */
    public static function sharesRowsWithoutCustomer(): bool
    {
        return true;
    }

    public function isStandard(): bool
    {
        return $this->customer_id === null;
    }
}
