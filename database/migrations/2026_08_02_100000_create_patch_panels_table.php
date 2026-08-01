<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patch_panels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('name');
            // Portanzahl. Legt beim Anlegen die Portzeilen an und bestimmt,
            // mit wie vielen Ports die Rack-Frontansicht das Feld zeichnet.
            // Bewusst nicht "ports": das waere der Name der Relation, und das
            // Attribut wuerde sie verdecken ($panel->ports gaebe die Zahl).
            $table->unsignedSmallInteger('port_count')->default(24);
            // 48er-Felder sind ueblicherweise 2 HE hoch.
            $table->unsignedTinyInteger('height_units')->default(1);
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patch_panels');
    }
};
