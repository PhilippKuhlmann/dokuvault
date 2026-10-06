<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many API requests came in, per hour and endpoint - agents and API
 * tokens. One row per hour, kind and path instead of one per request: an
 * agent polling every five minutes would otherwise fill the table with
 * rows nobody reads one by one. Enough to see when the load comes and
 * whether the server keeps up (duration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_stats', function (Blueprint $table) {
            $table->id();
            $table->dateTime('stunde');
            // agent | api
            $table->string('art', 20);
            // The route pattern, e.g. api/agent/checkin or api/agent/script/{agent}.
            $table->string('pfad', 150);
            $table->unsignedInteger('anzahl')->default(0);
            // Answers with status 500 and above.
            $table->unsignedInteger('fehler')->default(0);
            $table->unsignedBigInteger('dauer_ms_summe')->default(0);
            $table->unsignedInteger('dauer_ms_max')->default(0);

            $table->unique(['stunde', 'art', 'pfad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_stats');
    }
};
