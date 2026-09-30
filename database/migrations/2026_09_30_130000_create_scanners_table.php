<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serialNumber')->nullable();
            // Beschaffung (siehe HatBeschaffung), wie bei den anderen Geraeten.
            $table->date('purchase_date')->nullable();
            $table->date('warranty_until')->nullable();
            $table->date('eol_date')->nullable();
            $table->string('supplier')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'name']);
            $table->index('serialNumber');
        });

        // Wohin gescannt wird - Freigabe, E-Mail, FTP, Cloud. Ein eigener
        // Eintrag beim Kunden und kein Feld am Scanner: Dasselbe Ziel ist oft
        // auf mehreren Geraeten eingerichtet, und ein Scanner hat oft Dutzende.
        Schema::create('scan_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('kind', 20);
            $table->string('target');
            // Anmeldung am Ziel (FTP, Freigabe) - verknuepft statt kopiert,
            // wie die Zugangsdaten an den Geraeten.
            $table->foreignId('login_general_id')->nullable()->constrained('login_generals')->nullOnDelete();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'name']);
        });

        Schema::create('scan_target_scanner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scanner_id')->constrained('scanners')->cascadeOnDelete();
            $table->foreignId('scan_target_id')->constrained('scan_targets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scanner_id', 'scan_target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_target_scanner');
        Schema::dropIfExists('scan_targets');
        Schema::dropIfExists('scanners');
    }
};
