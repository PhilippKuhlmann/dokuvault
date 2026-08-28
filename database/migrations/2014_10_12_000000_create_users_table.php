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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique()->nullable();
            $table->string('password');
            $table->foreignId('role_id');
            $table->foreignId('customer_id')->nullable();
            $table->rememberToken();
            // Leer heisst: der Browsersprache folgen. Nur wer bewusst waehlt,
            // bekommt hier einen Wert - sonst muesste man Bestandsnutzer
            // erraten.
            $table->string('locale', 5)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
