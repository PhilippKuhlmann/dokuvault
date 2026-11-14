<?php

namespace Database\Seeders;

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Backup;
use App\Models\BackupRun;
use App\Models\Customer;
use App\Models\NetworkScanFinding;
use App\Models\Printer;
use App\Models\Site;
use App\Models\Ups;
use App\Support\AgentScript;
use App\Support\BackupHealth;
use App\Support\BackupSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What the agents leave behind - for the demo and the screenshots.
 *
 * Without it the agent page has no installed agents, the backups no runs,
 * Statistik -> API-Auslastung and -> Agenten are empty, and the dashboard
 * says "no backup yet". A demo that shows empty pages explains nothing.
 *
 * Plausible, not perfect: one agent is silent, one Veeam job failed, one
 * agent runs an old version - so the warnings have something to show. A
 * failed backup is a result the agent delivered, so its role stays green;
 * red on the agent page means the agent itself got no data (silent, or a
 * script that broke off).
 *
 *   php artisan db:seed --class=DemoAgentSeeder
 */
class DemoAgentSeeder extends Seeder
{
    public function run(): void
    {
        $kunde = Customer::where('slug', 'mustermann')->first();
        $standort = $kunde ? Site::where('customer_id', $kunde->id)->first() : null;
        if (! $kunde || ! $standort) {
            return;
        }

        [$token] = AgentToken::generateFor($kunde, $standort, 'Zentrale Hamburg', now()->addMonths(11)->endOfDay());
        $token->forceFill(['last_used_at' => now()->subMinutes(4)])->save();

        $this->agenten($kunde, $token);
        $this->netzwerkScan($kunde);
        $this->backupLaeufe($kunde);
        $this->apiStatistik();
        $this->eigeneSicherung();
    }

    private function agenten(Customer $kunde, AgentToken $token): void
    {
        $exe = AgentScript::exeVersion() ?: '26.10.06-1941';
        $linux = AgentScript::installerVersion('linux-agent.sh') ?: '26.10.05';

        $ok = fn (string $meldung, int $minuten) => ['ok' => true, 'message' => $meldung, 'at' => now()->subMinutes($minuten)->toIso8601String()];
        $fehler = fn (string $meldung, int $minuten) => ['ok' => false, 'message' => $meldung, 'at' => now()->subMinutes($minuten)->toIso8601String()];

        // [kind, hostname, version, roles, minutes since last contact, interval, results]
        $agenten = [
            ['windows', 'SRV-DC01', $exe, ['windows-server', 'windows-ad'], 12, null, [
                'windows-server' => $ok('Server gemeldet: SRV-DC01 (Windows Server 2022 Standard)', 12),
                'windows-ad' => $ok('37 Benutzer, 20 Gruppen, 112 Mitgliedschaften', 12),
            ]],
            ['windows', 'SRV-HV01', $exe, ['windows-server', 'hyperv', 'veeam'], 7, null, [
                'windows-server' => $ok('Server gemeldet: SRV-HV01', 7),
                'hyperv' => $ok('Host mit 6 VMs gemeldet', 7),
                // The job failed, the agent did not: it reported the failure.
                // That is a result for the backup page, not a red agent.
                'veeam' => $ok("1 Job gemeldet: 'Veeam – VMs täglich' (Failed)", 7),
            ]],
            // Silent for five hours - shows up as "meldet nicht".
            ['windows', 'SRV-FS01', '26.10.03-0912-a1b2c3d', ['windows-server', 'windows-backup'], 300, null, [
                'windows-server' => $ok('Server gemeldet: SRV-FS01', 300),
                'windows-backup' => $ok('Windows Server-Sicherung: letzte Sicherung erfolgreich', 300),
            ]],
            ['proxmox', 'pve01', $linux, ['proxmox'], 18, 30, [
                'proxmox' => $ok('Host pve01, 9 VMs, 4 Container, 2 Backup-Jobs', 18),
            ]],
            ['linux', 'web01', $linux, ['linux-server'], 41, 240, [
                'linux-server' => $ok('Debian 13, nginx, php8.3-fpm, mariadb', 41),
            ]],
            ['windows', 'PC-EMPFANG', $exe, ['windows-client'], 95, 1440, [
                'windows-client' => $ok('Arbeitsplatz gemeldet: PC-EMPFANG (Windows 11 Pro)', 95),
            ]],
        ];

        foreach ($agenten as [$art, $name, $version, $rollen, $minuten, $intervall, $ergebnisse]) {
            AgentInstallation::forceCreate([
                'customer_id' => $kunde->id,
                'agent_token_id' => $token->id,
                'kind' => $art,
                'machine_id' => (string) Str::uuid(),
                'hostname' => $name,
                'domain' => $art === 'windows' ? 'mustermann.local' : null,
                'version' => $version,
                'detected' => $rollen,
                'roles' => $rollen,
                'interval_minutes' => $intervall,
                'last_seen_at' => now()->subMinutes($minuten),
                'last_run_at' => now()->subMinutes($minuten),
                'last_results' => $ergebnisse,
            ]);
        }
    }

    /**
     * The network scan of SRV-DC01: two printers and a UPS waiting under
     * "Neu gefunden", one printer ignored, and one printer and the UPS of
     * the rack already tied to the scan, with their firmware.
     */
    private function netzwerkScan(Customer $kunde): void
    {
        $installation = AgentInstallation::where('customer_id', $kunde->id)->where('hostname', 'SRV-DC01')->first();
        $netz = AgentInstallation::scannableNetworks($kunde->id)->first(fn ($n) => (int) $n->cidr === 24);
        if (! $installation || ! $netz) {
            return;
        }

        $installation->forceFill([
            'roles' => [...$installation->roles, AgentInstallation::SCAN_ROLE],
            'scan_network_ids' => [$netz->id],
            'last_scan_at' => now()->subHours(3),
            'last_results' => [...$installation->last_results, AgentInstallation::SCAN_ROLE => [
                'ok' => true,
                'message' => '4 Drucker, 2 USV - 254 Adressen in 1 Netzen, 11s',
                'at' => now()->subHours(3)->toIso8601String(),
            ]],
        ])->save();

        $basis = substr((string) AgentInstallation::cidrOf($netz), 0, strrpos((string) AgentInstallation::cidrOf($netz), '.') + 1);
        $gesehen = now()->subHours(3);

        // [type, status, serial, name, manufacturer, model, firmware, host]
        foreach ([
            ['printer', 'new', 'CNB3K12345', 'HP-OG1-FLUR', 'HP', 'Color LaserJet Pro MFP M479fdw', '2507252_052345', 41],
            ['printer', 'new', 'E78123K4N567890', 'BRN3C2AF4A1B2C3', 'Brother', 'MFC-L8900CDW', 'ZA2510151510', 42],
            ['ups', 'new', 'AS2219123456', 'USV-Empfang', 'APC', 'Back-UPS Pro 900', '957.e4.I', 43],
            ['printer', 'ignored', 'VNB8K54321', 'LEIH-DRUCKER', 'HP', 'LaserJet M110we', '2405131_000317', 44],
        ] as [$art, $status, $serie, $name, $hersteller, $modell, $firmware, $host]) {
            NetworkScanFinding::forceCreate([
                'customer_id' => $kunde->id,
                'agent_installation_id' => $installation->id,
                'network_id' => $netz->id,
                'type' => $art,
                'identifier' => $serie,
                'status' => $status,
                'name' => $name,
                'manufacturer' => $hersteller,
                'model' => $modell,
                'serial' => $serie,
                'firmware' => $firmware,
                'ip' => $basis.$host,
                'last_seen_at' => $gesehen,
            ]);
        }

        // Already tied to the scan: kept current by it.
        if ($drucker = Printer::forCustomer($kunde)->whereNotNull('serialNumber')->orderBy('id')->first()) {
            $drucker->forceFill(['agent_identifier' => mb_strtoupper($drucker->serialNumber), 'firmware' => '2511041_000132'])->save();
        }
        if ($usv = Ups::forCustomer($kunde)->orderBy('id')->first()) {
            $usv->forceFill([
                'agent_identifier' => $usv->serialNumber ? mb_strtoupper($usv->serialNumber) : 'AS1904112233',
                'serialNumber' => $usv->serialNumber ?: 'AS1904112233',
                'firmware' => 'UPS 15.5 (ID1015)',
            ])->save();
        }
    }

    /**
     * Two weeks of nightly runs per backup; the Veeam job failed last night.
     * Plus the vzdump job the Proxmox agent reports itself.
     */
    private function backupLaeufe(Customer $kunde): void
    {
        Backup::forceCreate([
            'customer_id' => $kunde->id,
            'agent_identifier' => 'proxmox:pve01:backup-daily',
            'name' => 'vzdump – alle VMs',
            'software' => 'Proxmox VE (vzdump)',
            'source' => 'pve01 (9 VMs, 4 Container)',
            'destination' => 'PBS-Backup',
            'schedule' => 'täglich 21:00',
            'retention' => 'keep-daily=7, keep-weekly=4',
        ]);

        foreach (Backup::where('customer_id', $kunde->id)->get() as $i => $backup) {
            $veeam = str_contains((string) $backup->software, 'Veeam');
            $vzdump = str_contains((string) $backup->software, 'vzdump');
            $letzter = null;

            for ($tag = 14; $tag >= 1; $tag--) {
                $status = match (true) {
                    $veeam && $tag === 1 => 'failed',
                    $veeam && $tag === 6 => 'warning',
                    ! $veeam && $tag === 9 => 'failed',
                    default => 'ok',
                };
                $ende = now()->subDays($tag)->setTime($veeam ? 22 : ($vzdump ? 21 : 1), 37 + $i * 7);

                BackupRun::create(['backup_id' => $backup->id, 'status' => $status, 'finished_at' => $ende]);
                $letzter = [$status, $ende];
            }

            $backup->forceFill([
                'last_status' => $letzter[0],
                'last_run_at' => $letzter[1],
                'agent_identifier' => $veeam ? 'veeam:VMs-taeglich' : $backup->agent_identifier,
            ])->save();
        }
    }

    /**
     * A week of API requests per hour: the agents around the clock, the
     * API token during office hours - so the daily profile shows a shape.
     */
    private function apiStatistik(): void
    {
        $zeilen = [];
        for ($stunde = 7 * 24; $stunde >= 1; $stunde--) {
            $zeit = now()->subHours($stunde)->startOfHour();
            $buero = $zeit->isWeekday() && $zeit->hour >= 7 && $zeit->hour < 17;

            foreach ([
                ['agent', 'api/agent/checkin', 72, 18],
                ['agent', 'api/agent/report', 6, 25],
                ['agent', 'api/agent/windows-server', 4, 140],
                ['agent', 'api/agent/windows-ad', 1, 420],
                ['agent', 'api/agent/hyperv', 1, 210],
                ['agent', 'api/agent/proxmox', 2, 260],
                ['agent', 'api/agent/backup', 1, 90],
                ['api', 'api/customers', $buero ? 14 : 0, 60],
                ['api', 'api/servers', $buero ? 9 : 0, 85],
            ] as [$art, $pfad, $anzahl, $dauer]) {
                if ($anzahl === 0) {
                    continue;
                }
                $anzahl = max(1, $anzahl + random_int(-2, 2));
                $zeilen[] = [
                    'stunde' => $zeit,
                    'art' => $art,
                    'pfad' => $pfad,
                    'anzahl' => $anzahl,
                    'fehler' => $pfad === 'api/agent/backup' && $stunde === 20 ? 1 : 0,
                    'dauer_ms_summe' => $anzahl * ($dauer + random_int(0, 15)),
                    'dauer_ms_max' => $dauer * 3 + random_int(0, 40),
                ];
            }
        }

        foreach (array_chunk($zeilen, 500) as $teil) {
            DB::table('api_request_stats')->insert($teil);
        }
    }

    /**
     * DokuVault's own backup: last night, with archive password, local only -
     * the honest state of a demo without an external target.
     */
    private function eigeneSicherung(): void
    {
        BackupSettings::save(['backup_geheim_archivpasswort' => Str::random(24)]);
        BackupHealth::merken('local', null);
    }
}
