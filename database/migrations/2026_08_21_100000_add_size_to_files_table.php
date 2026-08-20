<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Die Groesse einer hochgeladenen Datei.
 *
 * Bisher stand in der Liste nur Name und Datum. Ob eine Datei 12 KB oder 18 MB
 * hat, sieht man erst nach dem Herunterladen - und genau das will man vorher
 * wissen, wenn man unterwegs am Mobilfunk haengt.
 *
 * Die Groesse wird beim Hochladen mitgeschrieben. Sie jedes Mal von der Platte
 * zu lesen waere ein Dateizugriff je Zeile und wuerde bei einer geloeschten
 * Datei zusaetzlich krachen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('files', 'size')) {
            Schema::table('files', function (Blueprint $table) {
                $table->unsignedBigInteger('size')->nullable()->after('extension');
            });
        }

        // Bestandsdateien nachtragen, soweit sie noch da sind.
        foreach (DB::table('files')->whereNull('size')->get(['id', 'file_path']) as $datei) {
            if ($datei->file_path && Storage::disk('local')->exists($datei->file_path)) {
                DB::table('files')->where('id', $datei->id)
                    ->update(['size' => Storage::disk('local')->size($datei->file_path)]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('files', 'size')) {
            Schema::table('files', fn (Blueprint $table) => $table->dropColumn('size'));
        }
    }
};
