<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signing in through a directory (an on-premises Active Directory over
 * LDAP; Entra ID later) next to local users.
 *
 * - users.auth_source: where the account signs in - "local" (password in
 *   DokuVault, invited customers, the local admin) or "ldap". A directory
 *   account has no usable password here; the directory checks it.
 * - users.external_id: the directory's own id (objectGUID). Stays the same
 *   when the account is renamed, so a renamed technician is still the same
 *   user with the same history.
 * - directory_group_roles: which directory group grants which role. The
 *   first matching row by position wins - that is how someone in two groups
 *   gets the higher role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_source', 10)->default('local')->after('password');
            $table->string('external_id')->nullable()->after('auth_source')->index();
        });

        Schema::create('directory_group_roles', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10)->default('ldap');
            // DN, sAMAccountName or common name of the group.
            $table->string('group');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['source', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_group_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn(['auth_source', 'external_id']);
        });
    }
};
