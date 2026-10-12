<?php

namespace App\Support;

use App\Models\Server;
use App\Models\VM;
use Illuminate\Support\Collection;

/**
 * What the server and VM cards show from an agent's report (agent_details),
 * as blocks for x-agentcard: title => rows [label, value, bar?].
 *
 * Here and not in the views: the shares (guests' memory of the host's, a
 * guest's cores of the host's) are sums over several records, and the cards
 * should only lay out.
 */
class AgentAnzeige
{
    /** @return array<string, array<int, array>> */
    public static function server(Server $server): array
    {
        $d = $server->agent_details ?? [];
        $eingebaut = (float) ($d['memory_gb'] ?? 0);

        // Only guests an agent reported: their memory and state are known.
        $gaeste = $server->gaeste->filter(fn (VM $vm) => $vm->agent_reported_at);
        $laufend = $gaeste->filter(fn (VM $vm) => ($vm->agent_details['status'] ?? null) === 'running');
        $ramLaufend = self::summe($laufend);
        $ramGesamt = self::summe($gaeste);

        $speicher = collect($d['storages'] ?? [])->map(fn ($st) => empty($st['total_gb'])
            // pvesm reports 0 for a storage it cannot reach (disabled, NAS
            // off) - "0 / 0 GB" read like an empty disk.
            ? [$st['name'], __('nicht verfügbar')]
            : [$st['name'], self::gb($st['used_gb'] ?? 0).' / '.self::gb($st['total_gb']), [
                'pct' => ($st['used_gb'] ?? 0) / $st['total_gb'] * 100,
                'warnen' => true,
            ]]
        )->all();

        return [
            __('System') => [
                [__('Proxmox VE'), $d['pve_version'] ?? null],
                [__('Kernel'), $d['kernel'] ?? null],
                [__('CPU'), $d['cpu'] ?? null],
            ],
            __('Arbeitsspeicher') => [
                [__('Eingebaut'), $eingebaut ? self::gb($eingebaut) : null],
                [__('Gäste'), $gaeste->isEmpty() ? null : __(':laufend laufend · :zugewiesen zugewiesen', [
                    'laufend' => self::gb($ramLaufend),
                    'zugewiesen' => self::gb($ramGesamt),
                ]), $eingebaut ? [
                    'pct' => $ramLaufend / $eingebaut * 100,
                    'dazu' => ($ramGesamt - $ramLaufend) / $eingebaut * 100,
                ] : null],
            ],
            __('Gäste') => $gaeste->isEmpty() ? [] : [
                [__('Gesamt'), __(':gesamt (:vm VM, :lxc LXC)', [
                    'gesamt' => $gaeste->count(),
                    'vm' => $gaeste->filter(fn (VM $vm) => ($vm->agent_details['type'] ?? null) === 'qemu')->count(),
                    'lxc' => $gaeste->filter(fn (VM $vm) => ($vm->agent_details['type'] ?? null) === 'lxc')->count(),
                ])],
                [__('Laufen'), (string) $laufend->count()],
            ],
            __('Speicher') => $speicher,
        ];
    }

    /**
     * The guest's load when the agent ran, against its own limits - not what
     * the host could give it. A stopped guest (or a report from before
     * 26.10.12) has no load: then only what is configured, without a bar.
     *
     * @return array<string, array<int, array>>
     */
    public static function vm(VM $vm): array
    {
        $d = $vm->agent_details ?? [];

        // A container without a core limit reports no cores: it may use all
        // of the host's, and its load is measured against those.
        $kerne = $d['cores'] ?? null;
        $kernText = match (true) {
            $kerne !== null => __(':anzahl Kerne', ['anzahl' => $kerne]),
            ($d['type'] ?? null) === 'lxc' => __('alle Kerne'),
            default => null,
        };
        $cpuLast = $d['cpu_pct'] ?? null;

        $ram = $d['memory_gb'] ?? null;
        $belegt = $d['memory_used_gb'] ?? null;
        $ramText = match (true) {
            $ram === null => null,
            $belegt !== null => __(':belegt von :limit', ['belegt' => self::gb($belegt), 'limit' => self::gb($ram)]),
            default => self::gb($ram),
        };

        return [
            __('System') => [
                [__('VMID'), isset($d['vmid']) ? (string) $d['vmid'] : null],
                [__('Status'), match ($d['status'] ?? null) {
                    'running' => __('läuft'),
                    'stopped' => __('gestoppt'),
                    'paused' => __('pausiert'),
                    default => $d['status'] ?? null,
                }],
                [__('CPU'), $kernText, $cpuLast !== null ? ['pct' => $cpuLast, 'warnen' => true] : null],
                [__('Arbeitsspeicher'), $ramText, $belegt !== null && $ram ? ['pct' => $belegt / $ram * 100, 'warnen' => true] : null],
            ],
        ];
    }

    protected static function summe(Collection $gaeste): float
    {
        return (float) $gaeste->sum(fn (VM $vm) => $vm->agent_details['memory_gb'] ?? 0);
    }

    /** "24,5 GB", "5,4 TB" - one decimal only where there is one. */
    public static function gb(float|int $gb): string
    {
        [$wert, $einheit] = $gb >= 1000 ? [$gb / 1000, 'TB'] : [$gb, 'GB'];
        $wert = round($wert, 1);
        $komma = app()->getLocale() === 'de' ? ',' : '.';

        return number_format($wert, fmod($wert, 1) ? 1 : 0, $komma, '').' '.$einheit;
    }
}
