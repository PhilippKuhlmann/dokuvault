<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installed agents (Windows service, Proxmox timer), one row per machine.
 *
 * A token is shared by many machines, so the machine identifies itself:
 * MachineGuid on Windows, /etc/machine-id on Linux. Before every run the
 * agent reports in (POST /api/agent/checkin) and gets back the roles it
 * should run - chosen here, not on the machine. Two DCs of one domain both
 * report as servers, but only one needs to report the domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('agent_token_id')->nullable()->constrained('agent_tokens')->nullOnDelete();
            // windows | proxmox - see config custom.dienste.
            $table->string('kind');
            $table->string('machine_id');
            $table->string('hostname');
            // DNS domain of the machine - two DCs of one domain need only one
            // AD report, two domains need one each.
            $table->string('domain')->nullable();
            $table->string('version')->nullable();
            // What the machine found itself, and what it should run.
            $table->json('detected')->nullable();
            $table->json('roles')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_installations');
    }
};
