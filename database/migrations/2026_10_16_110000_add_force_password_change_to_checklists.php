<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the AD script forces a password change at first sign-in. Off by
 * default - IT ticks it per checklist. The standard step no longer promises
 * the forced change in its title.
 */
return new class extends Migration
{
    private const OLD_TITLE = 'Kennwort setzen, Änderung bei der ersten Anmeldung erzwingen';

    private const NEW_TITLE = 'Start-Kennwort setzen';

    public function up(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->boolean('force_password_change')->default(false)->after('email');
        });

        DB::table('checklist_template_items')->whereNull('customer_id')
            ->where('title', self::OLD_TITLE)->update(['title' => self::NEW_TITLE]);
    }

    public function down(): void
    {
        DB::table('checklist_template_items')->whereNull('customer_id')
            ->where('title', self::NEW_TITLE)->update(['title' => self::OLD_TITLE]);

        Schema::table('checklists', function (Blueprint $table) {
            $table->dropColumn('force_password_change');
        });
    }
};
