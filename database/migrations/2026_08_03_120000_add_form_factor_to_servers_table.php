<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Bestandsserver gelten als 19-Zoll in voller Tiefe: Das ist der
            // haeufigere Fall, und die Angabe laesst sich je Server nachziehen.
            $table->string('form_factor')->default('rack')->after('model');
            $table->boolean('full_depth')->default(true)->after('form_factor');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['form_factor', 'full_depth']);
        });
    }
};
