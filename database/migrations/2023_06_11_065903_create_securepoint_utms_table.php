<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('securepoint_utms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('serialNumber')->nullable();
            $table->string('username');
            $table->string('password');
            $table->string('cloudBackupPassword')->nullable();
            $table->string('uscpin')->nullable();
            $table->string('urlAdmin');
            $table->string('urlUser')->nullable();
            $table->string('urlExternal')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('securepoint_utms');
    }
};
