<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the phone numbers live: the SIP account at the provider.
 *
 * A trunk carries a main number with an extension range, a multi-device
 * account a list of single numbers - hence both shapes as columns, the form
 * shows the one that fits. SIP credentials are not stored here but linked
 * via "Zugangsdaten": single numbers often have one login each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sip_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('provider');
            // trunk | single - see config custom.sip_account_types.
            $table->string('account_type')->nullable();
            $table->string('product')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('provider_customer_number')->nullable();
            $table->string('hotline')->nullable();
            $table->string('main_number')->nullable();
            $table->string('number_range')->nullable();
            $table->text('numbers')->nullable();
            $table->unsignedSmallInteger('channels')->nullable();
            $table->string('registrar')->nullable();
            $table->foreignId('phone_system_id')->nullable()->constrained('phone_systems')->nullOnDelete();
            $table->foreignId('internet_connection_id')->nullable()->constrained('internet_connections')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sip_accounts');
    }
};
