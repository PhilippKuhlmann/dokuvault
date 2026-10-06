<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily figures for Admin -> Statistik (System, Datenwachstum): one value
 * per day, figure and customer, written by statistik:schnappschuss.
 *
 * customer_id 0 instead of NULL for totals and system values: a unique
 * index treats NULLs as different, the same day could be written twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistik_werte', function (Blueprint $table) {
            $table->id();
            $table->date('datum');
            // e.g. server, vm, aduser (object counts) or db_bytes, disk_frei_bytes
            $table->string('kennzahl', 50);
            $table->unsignedBigInteger('customer_id')->default(0);
            $table->unsignedBigInteger('wert')->default(0);

            $table->unique(['datum', 'kennzahl', 'customer_id']);
            $table->index(['kennzahl', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistik_werte');
    }
};
