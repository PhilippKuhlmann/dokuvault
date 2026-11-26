<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the check target of a certificate was set by a customer account.
 * Then the nightly run checks it like the live button does: only public
 * addresses, resolved once and pinned - a name that resolves internally at
 * night is not probed on the customer's behalf. Staff keep checking
 * internal certificates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->boolean('check_public_only')->default(false)->after('check_port');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('check_public_only');
        });
    }
};
