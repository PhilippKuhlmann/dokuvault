<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log gets its own customer column and an index on the date.
 *
 * Before, "changes of this customer" went through the changed object: one
 * OR EXISTS per object type. With 2000 customers and 96,000 entries the
 * customer dashboard needed 250 ms for that query alone, and the admin
 * dashboard read every entry of two weeks to count them per day.
 *
 * No foreign key: an entry outlives its customer - "who deleted it?" is
 * asked after the deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('causer_id');
            $table->index('created_at');
            $table->index(['customer_id', 'created_at']);
        });

        // Existing entries: the customer itself, or the customer_id of the
        // changed object. A correlated subquery instead of UPDATE ... JOIN,
        // which SQLite does not know.
        DB::table('activity_log')
            ->where('subject_type', Customer::class)
            ->update(['customer_id' => DB::raw('subject_id')]);

        $typen = DB::table('activity_log')->whereNotNull('subject_type')
            ->where('subject_type', '!=', Customer::class)
            ->distinct()->pluck('subject_type');

        $g = DB::getQueryGrammar();

        foreach ($typen as $typ) {
            if (! class_exists($typ)) {
                continue;
            }
            $tabelle = (new $typ)->getTable();
            if (! Schema::hasColumn($tabelle, 'customer_id')) {
                continue;
            }

            DB::table('activity_log')
                ->where('subject_type', $typ)
                ->update(['customer_id' => DB::raw(
                    '(select '.$g->wrap($tabelle.'.customer_id').' from '.$g->wrapTable($tabelle)
                    .' where '.$g->wrap($tabelle.'.id').' = '.$g->wrap('activity_log.subject_id').')'
                )]);
        }
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('customer_id');
        });
    }
};
