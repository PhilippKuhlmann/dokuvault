<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PowerShell script for the AD part of a checklist.
 *
 * - action: what the script can do for an item (create_ad_user, set_password,
 *   add_group, disable_ad_user, reset_password, remove_group). Items without
 *   one stay manual.
 * - script_token_hash: the key a downloaded script reports back with. Only the
 *   hash is stored; it is valid for one checklist and until it expires.
 */
return new class extends Migration
{
    private const STANDARD_ACTIONS = [
        'onboarding' => [
            'AD-Benutzer {username} anlegen' => 'create_ad_user',
            'Kennwort setzen, Änderung bei der ersten Anmeldung erzwingen' => 'set_password',
        ],
        'offboarding' => [
            'AD-Benutzer {username} deaktivieren' => 'disable_ad_user',
            'Kennwort zurücksetzen, aktive Sitzungen abmelden' => 'reset_password',
        ],
    ];

    public function up(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->string('action', 30)->nullable()->after('title');
        });

        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('action', 30)->nullable()->after('title');
        });

        Schema::table('checklists', function (Blueprint $table) {
            $table->string('script_token_hash', 64)->nullable()->unique();
            $table->timestamp('script_token_expires_at')->nullable();
            $table->foreignId('script_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('script_reported_at')->nullable();
        });

        foreach (self::STANDARD_ACTIONS as $type => $actions) {
            $template = DB::table('checklist_templates')->where('type', $type)->value('id');

            foreach ($actions as $title => $action) {
                DB::table('checklist_template_items')->where('checklist_template_id', $template)
                    ->whereNull('customer_id')->where('title', $title)->update(['action' => $action]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('script_requested_by');
            $table->dropUnique(['script_token_hash']);
            $table->dropColumn(['script_token_hash', 'script_token_expires_at', 'script_reported_at']);
        });

        Schema::table('checklist_items', fn (Blueprint $table) => $table->dropColumn('action'));
        Schema::table('checklist_template_items', fn (Blueprint $table) => $table->dropColumn('action'));
    }
};
