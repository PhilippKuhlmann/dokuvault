<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internet_connections', function (Blueprint $table) {
            // Optional: Viele Anschluesse bringen zusaetzlich zur WAN-IP ein
            // geroutetes Netz mit, etwa ein /28 samt eigenem Gateway. Beides
            // nullable - der Normalfall ist eine einzelne dynamische Adresse.
            $table->string('subnet')->nullable()->after('wan_ip');
            $table->string('subnet_gateway')->nullable()->after('subnet');
        });
    }

    public function down(): void
    {
        Schema::table('internet_connections', function (Blueprint $table) {
            $table->dropColumn(['subnet', 'subnet_gateway']);
        });
    }
};
