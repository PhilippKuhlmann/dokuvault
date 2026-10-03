<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The runs of a backup job as the agents report them - "did the last ten
 * nights work?" instead of only the last one. Unique per job and end time:
 * the agents send their recent runs on every report, each is kept once.
 *
 * Plus the right to see the overview in the admin area (admin_backup), for
 * existing installations given to every role that may see the log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->constrained('backups')->cascadeOnDelete();
            // ok | warning | failed (Backup::STATUS)
            $table->string('status');
            $table->timestamp('finished_at');
            $table->timestamps();

            $table->unique(['backup_id', 'finished_at']);
        });

        $id = DB::table('permissions')->where('name', 'admin_backup')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'admin_backup',
                'description' => 'Backups aller Kunden sehen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $vorbild = DB::table('permissions')->where('name', 'admin_activity')->value('id');
        if ($vorbild) {
            $rollen = DB::table('permission_role')->where('permission_id', $vorbild)->pluck('role_id');
            $schonDa = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');
            DB::table('permission_role')->insert(
                $rollen->diff($schonDa)->map(fn ($rolle) => ['permission_id' => $id, 'role_id' => $rolle])->values()->all()
            );
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'admin_backup')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        Schema::dropIfExists('backup_runs');
    }
};
