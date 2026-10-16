<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Network drives as their own documentation type, on a server, VM or NAS
 * (host_type/host_id, as with the AD domain's machines).
 *
 * The onboarding form reads its drives from here: in_onboarding_form switches
 * a drive on for the form, the AD groups give the checklist the group to
 * assign. Until now the checklist settings kept their own drive list - that
 * moves here, so checklist_options keeps programs and hardware only.
 *
 * Rights: whoever has the NAS right gets the network drive right.
 */
return new class extends Migration
{
    private const RIGHTS = [
        'viewAny' => 'sehen',
        'create' => 'erstellen',
        'update' => 'bearbeiten',
        'delete' => 'löschen',
    ];

    public function up(): void
    {
        Schema::create('network_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('host');
            $table->string('name');
            $table->string('drive_letter', 2)->nullable();
            $table->string('path');
            $table->foreignId('ad_group_read_id')->nullable()->constrained('ad_groups')->nullOnDelete();
            $table->foreignId('ad_group_write_id')->nullable()->constrained('ad_groups')->nullOnDelete();
            $table->boolean('sensitive')->default(false);
            $table->boolean('in_onboarding_form')->default(false);
            $table->text('description')->nullable();
            $table->string('agent_identifier')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('checklist_options')->where('kind', 'drive')->delete();
        Schema::table('checklist_options', function (Blueprint $table) {
            $table->dropColumn(['drive_letter', 'path']);
        });

        foreach (self::RIGHTS as $right => $description) {
            $id = DB::table('permissions')->where('name', "networkshare_{$right}")->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => "networkshare_{$right}",
                    'description' => "NetworkShare {$description}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $model = DB::table('permissions')->where('name', "nas_{$right}")->value('id');
            if (! $model) {
                continue;
            }

            $roles = DB::table('permission_role')->where('permission_id', $model)->pluck('role_id');
            $existing = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');
            DB::table('permission_role')->insert(
                $roles->diff($existing)->map(fn ($role) => ['permission_id' => $id, 'role_id' => $role])->values()->all()
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', 'like', 'networkshare\_%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::table('checklist_options', function (Blueprint $table) {
            $table->string('drive_letter', 2)->nullable();
            $table->string('path')->nullable();
        });

        Schema::dropIfExists('network_shares');
    }
};
