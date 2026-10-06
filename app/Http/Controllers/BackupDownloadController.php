<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download of one of DokuVault's own backups (Admin -> Einstellungen ->
 * Backup), local or from the external target. Streamed, so a large archive
 * never sits in memory.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $disk = (string) $request->query('disk');
        $pfad = (string) $request->query('pfad');

        // Only a disk the backup writes to, and only a file the package lists
        // as a backup there - not any path on the server.
        abort_unless(in_array($disk, config('backup.backup.destination.disks', []), true), 404);
        $vorhanden = BackupDestination::create($disk, config('backup.backup.name'))
            ->backups()
            ->contains(fn ($sicherung) => $sicherung->path() === $pfad);
        abort_unless($vorhanden, 404);

        return Storage::disk($disk)->download($pfad, basename($pfad));
    }
}
