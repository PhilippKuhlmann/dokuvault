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
        Schema::create('securepoint_umas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('username');
            $table->string('password');
            $table->longText('encryptionkey');
            $table->string('urlAdmin');
            $table->string('urlUser')->nullable();
            if (! Schema::hasColumn('securepoint_umas', 'manufacturer')) {
                $table->string('manufacturer')->nullable();
            }
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
        Schema::dropIfExists('securepoint_umas');
    }
};
