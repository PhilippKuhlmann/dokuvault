<?php

use App\Models\RackItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activity entries of rack items carried no customer: the item has no
 * customer_id of its own, only its rack. A NULL entry counts as "no
 * customer" and shows to every team - so the customer comes from the rack.
 * Items deleted since stay as they are; there is no rack left to ask.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_log')
            ->where('subject_type', RackItem::class)
            ->whereNull('customer_id')
            ->update([
                'customer_id' => DB::raw('(SELECT racks.customer_id FROM rack_items JOIN racks ON racks.id = rack_items.rack_id WHERE rack_items.id = activity_log.subject_id)'),
            ]);
    }

    public function down(): void
    {
        // The NULLs were the defect - nothing to restore.
    }
};
