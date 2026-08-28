<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('provider')->nullable();
            $table->string('product')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('connection_type')->nullable();
            $table->string('bandwidth_down')->nullable();
            $table->string('bandwidth_up')->nullable();
            $table->string('wan_ip')->nullable();
            $table->string('hotline')->nullable();
            $table->text('notes')->nullable();
            // Optional: Viele Anschluesse bringen zusaetzlich zur WAN-IP ein
            // geroutetes Netz mit, etwa ein /28 samt eigenem Gateway. Beides
            // nullable - der Normalfall ist eine einzelne dynamische Adresse.
            $table->string('subnet')->nullable();
            $table->string('subnet_gateway')->nullable();
            $table->string('pppoe_user')->nullable();
            $table->text('pppoe_password')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_connections');
    }
};
