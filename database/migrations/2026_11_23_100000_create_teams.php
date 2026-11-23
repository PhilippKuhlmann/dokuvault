<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teams: which staff users may see which customers.
 *
 * A customer without a team is open to every staff user, as before. A
 * customer with one or more teams is visible to their members only
 * (App\Support\CustomerAccess). The level per assignment - full, read,
 * read without passwords - is stored here already and enforced later.
 *
 * Plus the right to manage teams (admin_team), for existing installations
 * given to every role that may manage users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('customer_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            // full | read | read_no_secrets (CustomerAccess::LEVELS)
            $table->string('level')->default('full');
            $table->timestamps();

            $table->unique(['customer_id', 'team_id']);
        });

        $id = DB::table('permissions')->where('name', 'admin_team')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'admin_team',
                'description' => 'Teams verwalten',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $model = DB::table('permissions')->where('name', 'admin_user')->value('id');
        if ($model) {
            $roles = DB::table('permission_role')->where('permission_id', $model)->pluck('role_id');
            $already = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');
            DB::table('permission_role')->insert(
                $roles->diff($already)->map(fn ($role) => ['permission_id' => $id, 'role_id' => $role])->values()->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_team');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');

        $id = DB::table('permissions')->where('name', 'admin_team')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
