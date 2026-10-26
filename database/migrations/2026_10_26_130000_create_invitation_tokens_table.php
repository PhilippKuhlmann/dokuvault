<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations get their own token table. They shared password_resets with
 * "forgot password", so a reset link (meant for one hour) also worked as an
 * invitation link for a week.
 *
 * Open invitations are copied over, so links already sent keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_tokens', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        $invited = DB::table('users')->whereNotNull('invited_at')->whereNotNull('email')->pluck('email');

        DB::table('password_resets')->whereIn('email', $invited)->orderBy('created_at')->get()
            ->each(fn ($row) => DB::table('invitation_tokens')->insert([
                'email' => $row->email, 'token' => $row->token, 'created_at' => $row->created_at,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_tokens');
    }
};
