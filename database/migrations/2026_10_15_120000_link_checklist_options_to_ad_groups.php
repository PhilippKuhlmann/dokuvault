<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programs and hardware of the onboarding form point to a documented AD
 * group instead of naming one in free text - the same as network drives.
 * Names that match a group of the customer are carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_options', function (Blueprint $table) {
            $table->foreignId('ad_group_id')->nullable()->after('name')->constrained('ad_groups')->nullOnDelete();
        });

        foreach (DB::table('checklist_options')->whereNotNull('ad_group')->get() as $option) {
            $group = DB::table('ad_groups')->where('customer_id', $option->customer_id)
                ->where('name', $option->ad_group)->whereNull('deleted_at')->value('id');

            if ($group) {
                DB::table('checklist_options')->where('id', $option->id)->update(['ad_group_id' => $group]);
            }
        }

        Schema::table('checklist_options', function (Blueprint $table) {
            $table->dropColumn('ad_group');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_options', function (Blueprint $table) {
            $table->string('ad_group')->nullable();
        });

        foreach (DB::table('checklist_options')->whereNotNull('ad_group_id')->get() as $option) {
            DB::table('checklist_options')->where('id', $option->id)
                ->update(['ad_group' => DB::table('ad_groups')->where('id', $option->ad_group_id)->value('name')]);
        }

        Schema::table('checklist_options', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_group_id');
        });
    }
};
