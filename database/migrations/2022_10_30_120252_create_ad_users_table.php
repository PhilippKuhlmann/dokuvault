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
        Schema::create('ad_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->boolean('hidden')->default(false);
            $table->string('email')->nullable();
            $table->boolean('enabled')->nullable();
            $table->string('agent_identifier')->nullable()->index();
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
        Schema::dropIfExists('ad_users');
    }
};
