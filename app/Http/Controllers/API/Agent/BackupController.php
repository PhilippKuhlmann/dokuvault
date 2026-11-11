<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsBackups;
use App\Models\Backup;
use App\Models\Server;
use Illuminate\Http\Request;

/** Backup software reports its jobs and their last runs: Veeam, Windows Server Backup. */
class BackupController extends AgentEndpoint
{
    use ReportsBackups;

    /**
     * Backup jobs from Veeam B&R or the Windows Server-Sicherung (scripts
     * veeam.ps1, windows-backup.ps1). Proxmox sends its vzdump jobs with
     * the host report instead.
     */
    public function backup(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $data = $request->validate($this->backupRegeln('jobs'));

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'backups_documented' => $this->backupsDokumentieren($customer->id, $data['jobs'] ?? []),
        ]);
    }
}
