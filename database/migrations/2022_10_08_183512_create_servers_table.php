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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('operating_system_id')->constrained('operating_systems')->onDelete('cascade')->nullable();
            $table->string('name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serialNumber')->nullable();
            $table->string('bmcIp')->nullable();
            $table->string('bmcUser')->nullable();
            $table->string('bmcPassword')->nullable();
            $table->string('services')->nullable();
            $table->string('remoteID')->nullable();
            $table->string('remotePassword')->nullable();
            $table->string('agent_identifier')->nullable()->index();
            // Bestandsserver gelten als 19-Zoll in voller Tiefe: Das ist der
            // haeufigere Fall, und die Angabe laesst sich je Server nachziehen.
            $table->string('form_factor')->default('rack');
            $table->boolean('full_depth')->default(true);
            // Der Rack-Editor liest height_units beim Einbau. Ohne die Spalte
            // bekam jeder Server eine Hoeheneinheit, unabhaengig von der
            // Bauhoehe. Standard 1, weil das der haeufigste Fall ist.
            $table->unsignedTinyInteger('height_units')->default(1);
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
        Schema::dropIfExists('servers');
    }
};
