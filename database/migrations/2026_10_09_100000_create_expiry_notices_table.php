<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expiry mails: who wants them, and what each one was already told.
 *
 * The notices table is the memory of the daily run. Without it every mail
 * would repeat everything that is still expiring - after a week nobody
 * reads them any more. A notice is per stage ("soon", "expired") and per
 * date: a renewed certificate with a new date counts as a new item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('expiry_mail')->default(false)->after('locale');
        });

        Schema::create('expiry_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('stage', 16);
            $table->date('due_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'subject_type', 'subject_id', 'stage', 'due_date'], 'expiry_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_notices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('expiry_mail');
        });
    }
};
