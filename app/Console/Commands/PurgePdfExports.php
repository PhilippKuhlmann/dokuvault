<?php

namespace App\Console\Commands;

use App\Models\PdfExport;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes old PDF exports.
 *
 * The files hold every password of a customer in plain text. Once collected
 * there is no reason for them to stay on disk - whoever still needs one
 * orders it again.
 */
class PurgePdfExports extends Command
{
    protected $signature = 'pdf:purge';

    protected $description = 'Deletes PDF exports older than the retention period';

    public function handle(): int
    {
        $cutoff = now()->subHours(Setting::pdfStunden());
        $count = 0;

        // Deleting the row deletes the file as well, see PdfExport::booted().
        PdfExport::where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($exports) use (&$count) {
                foreach ($exports as $export) {
                    $export->delete();
                    $count++;
                }
            });

        // Files without a row: left behind by a database cascade (a deleted
        // user or customer before the model hooks existed) or a crash between
        // writing the file and the row. Same retention, by file age.
        $known = PdfExport::whereNotNull('path')->pluck('path')->flip();
        foreach (Storage::disk('local')->allFiles('pdf-exports') as $file) {
            if (! $known->has($file) && Storage::disk('local')->lastModified($file) < $cutoff->getTimestamp()) {
                Storage::disk('local')->delete($file);
                $count++;
            }
        }

        $this->info($count.' PDF exports purged.');

        return self::SUCCESS;
    }
}
