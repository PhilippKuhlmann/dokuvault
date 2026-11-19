<?php

namespace App\Support;

use App\Models\Backup;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Models\VM;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The server map of a customer, one per site: cluster -> hosts -> guests,
 * each with what it is made of - CPU, cores, memory, operating system,
 * address.
 *
 * Built from what is documented and what the agents reported
 * (agent_details), the same values the server and VM cards show. Configured
 * values only: cores and memory a guest was given, not its load - this is
 * documentation, not monitoring.
 *
 * Nothing is dropped: servers without guests and guests without a host are
 * listed below the map, so it also shows gaps in the documentation.
 *
 * What an agent reports comes first; the CPU, cores and memory entered by
 * hand fill in where no agent reports (details()).
 */
class ServerMap
{
    protected Customer $customer;

    /** Whether the user may see the guests by name, or only count them. */
    protected bool $showGuests;

    /** Model class => list route prefix or null, resolved once per request. */
    protected array $lists = [];

    /** Leave stopped guests out of the lists (they still count on the host). */
    protected bool $hideStopped = false;

    /** The rights of the viewer - the current user, or whoever ordered the PDF. */
    protected GateContract $gate;

    /**
     * The vzdump jobs a Proxmox agent reported, by the cluster or host they
     * belong to (the middle of "proxmox/<cluster or host>/<job>"):
     * ['all' => bool, 'except' => ids, 'ids' => ids, 'pool' => bool].
     */
    protected array $backupJobs = [];

    public function build(Customer $customer, ?int $siteId = null, ?User $viewer = null, bool $hideStopped = false): array
    {
        $this->customer = $customer;
        $this->gate = $viewer ? Gate::forUser($viewer) : Gate::getFacadeRoot();
        $this->showGuests = $this->gate->allows('vm_viewAny');
        $this->hideStopped = $hideStopped;
        $this->backupJobs = $this->backupJobs($customer);

        $sites = $customer->sites()
            ->when($siteId, fn ($query) => $query->whereKey($siteId))
            ->orderBy('name')
            ->get();

        $clusters = Cluster::where('customer_id', $customer->id)->orderBy('name')->get();
        $servers = Server::where('customer_id', $customer->id)
            ->with(['operatingSystem', 'ipAddresses'])
            ->orderBy('name')
            ->get();
        $guests = VM::where('customer_id', $customer->id)
            ->with(['operatingSystem', 'ipAddresses', 'cluster'])
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $sites
            ->map(fn (Site $site) => $this->site($site, $clusters, $servers, $guests))
            ->all();
    }

    /**
     * A column per cluster with its hosts, then one per host outside a
     * cluster that runs guests. A host belongs to its cluster's site, a
     * guest to its host - wherever their own site_id points.
     */
    protected function site(Site $site, Collection $clusters, Collection $servers, Collection $guests): array
    {
        $byHost = $guests->whereNotNull('server_id')->groupBy('server_id');
        $hostIds = $servers->pluck('id')->all();
        $clusterIds = $clusters->pluck('id')->all();
        $columns = [];

        foreach ($clusters->where('site_id', $site->id) as $cluster) {
            $members = $servers->where('cluster_id', $cluster->id);

            // Guests of the cluster whose host is not known (or not here):
            // in a cluster a guest moves between nodes, the host is a snapshot.
            $loose = $guests
                ->where('cluster_id', $cluster->id)
                ->filter(fn (VM $vm) => ! $vm->server_id || ! in_array($vm->server_id, $hostIds, true))
                ->values();

            $columns[] = [
                'cluster' => $this->clusterNode($cluster, $members),
                'hosts_in_cluster' => true,
                'hosts' => $members->map(fn (Server $s) => $this->host($s, $byHost->get($s->id, collect())))->values()->all(),
                'loose' => $this->listOrNull($loose),
            ];
        }

        $standalone = [];

        foreach ($servers->where('site_id', $site->id) as $server) {
            if ($server->cluster_id && in_array($server->cluster_id, $clusterIds, true)) {
                continue;
            }

            $own = $byHost->get($server->id, collect());

            if ($own->isEmpty()) {
                $standalone[] = $this->host($server, $own, false);

                continue;
            }

            $columns[] = ['cluster' => null, 'hosts' => [$this->host($server, $own, false)], 'loose' => null];
        }

        $unhosted = $guests
            ->where('site_id', $site->id)
            ->filter(fn (VM $vm) => (! $vm->server_id || ! in_array($vm->server_id, $hostIds, true))
                && (! $vm->cluster_id || ! in_array($vm->cluster_id, $clusterIds, true)))
            ->values();

        return [
            'site' => $site,
            'columns' => $columns,
            'standalone' => $standalone,
            'unhosted' => $this->listOrNull($unhosted),
        ];
    }

    /**
     * The cluster on top: its kind, what its members add up to and the
     * storage they share - Ceph, NFS, a backup server - once, not on every
     * node again.
     */
    protected function clusterNode(Cluster $cluster, Collection $members): array
    {
        $cores = $members->sum(fn (Server $s) => (int) ($this->details($s)['cores'] ?? 0));
        $memory = $members->sum(fn (Server $s) => (float) ($this->details($s)['memory_gb'] ?? 0));

        $shared = $members
            ->flatMap(fn (Server $s) => $this->storages($s, true))
            ->groupBy('name')
            ->map(fn (Collection $same) => $same->sortByDesc('total_gb')->first())
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'name' => $cluster->name,
            'type' => $cluster->typeLabel() ?: __('Cluster'),
            'url' => $this->url($cluster),
            'facts' => array_values(array_filter([
                trans_choice(':count Host|:count Hosts', $members->count()),
                $cores ? trans_choice(':count Kern|:count Kerne', $cores) : null,
                $memory ? __(':menge RAM', ['menge' => AgentReportCards::gb($memory)]) : null,
            ])),
            'storages' => $shared,
        ];
    }

    /**
     * A server with its hardware and, below it, its guests.
     *
     * Memory given to guests against memory built in - overcommitment is a
     * fact of the setup, not a load value. Running and stopped guests apart:
     * a host with 27 switched-off guests looked three times overbooked
     * while what runs fitted easily. Amber only when the running guests
     * alone were given more than is built in.
     */
    protected function host(Server $server, Collection $guests, bool $inCluster = true): array
    {
        $d = $this->details($server);
        $memory = (float) ($d['memory_gb'] ?? 0);
        $assigned = (float) $guests->sum(fn (VM $vm) => $this->details($vm)['memory_gb'] ?? 0);
        $running = (float) $guests->filter(fn (VM $vm) => ! $this->stopped($vm))->sum(fn (VM $vm) => $this->details($vm)['memory_gb'] ?? 0);
        $runningPct = $memory ? min(100, $running / $memory * 100) : 0;

        return [
            'name' => $server->name,
            'type' => $guests->isEmpty() ? __('Server') : __('Host'),
            'url' => $this->url($server),
            'model' => trim($server->manufacturer.' '.$server->model) ?: null,
            'os' => $server->operatingSystem?->name ?? (isset($d['pve_version']) ? 'Proxmox VE '.$d['pve_version'] : null),
            // For the EOL badge - the same one as in the lists.
            'osModel' => $server->operatingSystem,
            'services' => $this->services($server),
            'cpu' => $d['cpu'] ?? null,
            'facts' => array_values(array_filter([
                isset($d['cores']) ? trans_choice(':count Kern|:count Kerne', (int) $d['cores']) : null,
                $memory ? __(':menge RAM', ['menge' => AgentReportCards::gb($memory)]) : null,
            ])),
            'address' => $this->address($server),
            // In a cluster the shared storage stands on the cluster node.
            'storages' => $this->storages($server, $inCluster ? false : null),
            'assigned' => $memory && $assigned ? [
                'text' => $running < $assigned
                    ? __(':laufend laufend · :gesamt vergeben', [
                        'laufend' => AgentReportCards::gb($running),
                        'gesamt' => AgentReportCards::gb($assigned),
                    ])
                    : __(':zugewiesen von :eingebaut an Gäste vergeben', [
                        'zugewiesen' => AgentReportCards::gb($assigned),
                        'eingebaut' => AgentReportCards::gb($memory),
                    ]),
                'title' => __('Eingebaut :eingebaut · an laufende Gäste :laufend · an alle Gäste zusammen :gesamt', [
                    'eingebaut' => AgentReportCards::gb($memory),
                    'laufend' => AgentReportCards::gb($running),
                    'gesamt' => AgentReportCards::gb($assigned),
                ]),
                // Solid: running guests. Pale behind it: the stopped ones,
                // as far as the bar reaches.
                'pct' => $runningPct,
                'dazu' => max(0, min(100 - $runningPct, ($assigned - $running) / $memory * 100)),
                'over' => $running > $memory,
            ] : null,
            'guests' => $this->listOrNull($guests),
            // Switched off and left out by the switch: said on the host, so
            // an empty branch is not taken for a host without guests.
            'hidden' => $this->hideStopped ? $guests->filter(fn (VM $vm) => $this->stopped($vm))->count() : 0,
        ];
    }

    /** The guest list, or null when nothing of it is left to show. */
    protected function listOrNull(Collection $guests): ?array
    {
        $list = $guests->isEmpty() ? null : $this->guestList($guests);

        return $list && $list['groups'] ? $list : null;
    }

    /**
     * Guests grouped by kind - VMs, then containers - with a count, or only
     * the count for a user who may not see the VM list. The kind is a field
     * of the VM (by hand or from the agent), so every guest has one.
     */
    protected function guestList(Collection $guests): array
    {
        $order = ['vm', 'lxc'];
        $labels = ['vm' => __('VMs'), 'lxc' => __('Container')];

        $groups = $guests
            ->groupBy(fn (VM $vm) => $vm->istContainer() ? 'lxc' : 'vm')
            ->sortBy(fn ($group, string $kind) => array_search($kind, $order, true))
            ->map(function (Collection $group, string $kind) use ($labels) {
                $shown = $this->hideStopped ? $group->reject(fn (VM $vm) => $this->stopped($vm)) : $group;

                return [
                    'kind' => $kind,
                    'label' => $labels[$kind],
                    'count' => $shown->count(),
                    // Left out by the switch - said, so the count is not taken
                    // for all there is.
                    'hidden' => $group->count() - $shown->count(),
                    'unprotected' => $shown->filter(fn (VM $vm) => $this->backedUp($vm) === false)->count(),
                    'guests' => $this->showGuests ? $shown->map(fn (VM $vm) => $this->guest($vm))->values()->all() : [],
                ];
            })
            // A kind whose guests are all switched off and hidden goes.
            ->filter(fn (array $group) => $group['count'] > 0)
            ->values()
            ->all();

        return [
            'count' => array_sum(array_column($groups, 'count')),
            'hidden' => array_sum(array_column($groups, 'hidden')),
            'unprotected' => array_sum(array_column($groups, 'unprotected')),
            'visible' => $this->showGuests,
            'groups' => $groups,
        ];
    }

    protected function guest(VM $vm): array
    {
        $d = $this->details($vm);

        return [
            'name' => $vm->name,
            'url' => $this->url($vm),
            // Stopped guests stand muted - a template or a spare is part of
            // the setup, but nothing runs there.
            'stopped' => $this->stopped($vm),
            // What it was given, as short chips; the operating system on a
            // line of its own - in one line with the rest it was cut off.
            'specs' => array_values(array_filter([
                isset($d['cores']) ? trans_choice(':count Kern|:count Kerne', (int) $d['cores']) : null,
                isset($d['memory_gb']) ? __(':menge RAM', ['menge' => AgentReportCards::gb((float) $d['memory_gb'])]) : null,
                empty($d['disk_gb']) ? null : __(':menge Disk', ['menge' => AgentReportCards::gb((float) $d['disk_gb'])]),
            ])),
            'os' => $vm->operatingSystem?->name,
            'osModel' => $vm->operatingSystem,
            'services' => $this->services($vm),
            'address' => $this->address($vm),
            // false: a vzdump job of its cluster or host is known and leaves
            // it out. null: nothing to tell (no Proxmox guest, no job known).
            'backup' => $this->backedUp($vm),
        ];
    }

    /**
     * What the device is made of: the agent's report first, the values
     * entered by hand where it has none - a host without agent, a vServer.
     */
    protected function details(Server|VM $device): array
    {
        $reported = array_filter($device->agent_details ?? [], fn ($v) => $v !== null && $v !== '');
        $manual = array_filter([
            'cpu' => $device instanceof Server ? $device->cpu : null,
            'cores' => $device->cores,
            'memory_gb' => $device->memory_gb,
            'disk_gb' => $device instanceof VM ? $device->disk_gb : null,
        ], fn ($v) => $v !== null && $v !== '');

        return $reported + $manual;
    }

    /**
     * The storages a Proxmox host reported, by capacity - what is there, not
     * how full it is (documentation, not monitoring). One it could not reach
     * (0 GB) is left out.
     *
     * $shared: true only the shared ones (network, Ceph, backup server),
     * false only the local ones, null all.
     */
    protected function storages(Server $server, ?bool $shared = null): array
    {
        $local = ['dir', 'lvm', 'lvmthin', 'zfspool', 'btrfs'];

        return collect($server->agent_details['storages'] ?? [])
            ->filter(fn ($st) => is_array($st) && filled($st['name'] ?? null) && ! empty($st['total_gb']))
            ->filter(fn ($st) => $shared === null
                || $shared === ! in_array(mb_strtolower((string) ($st['type'] ?? '')), $local, true))
            ->map(fn ($st) => [
                'name' => (string) $st['name'],
                'type' => (string) ($st['type'] ?? ''),
                'total_gb' => (float) $st['total_gb'],
                'size' => AgentReportCards::gb((float) $st['total_gb']),
            ])
            ->values()
            ->all();
    }

    /**
     * The vzdump jobs of the customer, from their "source" as the Proxmox
     * agent writes it: "alle Gaeste[ ausser 101, 102]", "VMs 100, 101" or
     * "Pool name".
     */
    protected function backupJobs(Customer $customer): array
    {
        $jobs = [];

        $reported = Backup::where('customer_id', $customer->id)
            ->where('agent_identifier', 'like', 'proxmox/%')
            ->get(['agent_identifier', 'source']);

        foreach ($reported as $backup) {
            $parts = explode('/', (string) $backup->agent_identifier);
            if (count($parts) < 3 || $parts[1] === '') {
                continue;
            }

            $scope = mb_strtolower($parts[1]);
            $jobs[$scope] ??= ['all' => false, 'except' => null, 'ids' => [], 'pool' => false];
            $source = trim((string) $backup->source);
            $ids = fn (string $list) => array_map('intval', preg_split('/\s*,\s*/', trim($list), -1, PREG_SPLIT_NO_EMPTY));

            if (preg_match('/^alle G(?:ae|ä)ste(?:\s+au(?:ss|ß)er\s+(.+))?$/iu', $source, $m)) {
                $except = isset($m[1]) ? $ids($m[1]) : [];
                $jobs[$scope]['all'] = true;
                // Two "all" jobs: only what both leave out is left out.
                $jobs[$scope]['except'] = $jobs[$scope]['except'] === null
                    ? $except
                    : array_values(array_intersect($jobs[$scope]['except'], $except));
            } elseif (preg_match('/^VMs\s+(.+)$/iu', $source, $m)) {
                $jobs[$scope]['ids'] = array_merge($jobs[$scope]['ids'], $ids($m[1]));
            } elseif ($source !== '') {
                // A pool, or something not understood: undecidable.
                $jobs[$scope]['pool'] = true;
            }
        }

        return $jobs;
    }

    /**
     * Whether a vzdump job takes this guest along: true, false, or null
     * when that cannot be told - not a Proxmox guest, no job reported for
     * its cluster or host (it may be backed up some other way), or a pool
     * job whose members are not known.
     */
    protected function backedUp(VM $vm): ?bool
    {
        $vmid = $vm->agent_details['vmid'] ?? null;
        if (! is_numeric($vmid) || ! $this->backupJobs) {
            return null;
        }

        $scopes = array_filter([
            // "<host>/qemu/<vmid>" - the host as the agent names itself.
            str_contains((string) $vm->agent_identifier, '/') ? strstr((string) $vm->agent_identifier, '/', true) : null,
            $vm->cluster && str_starts_with((string) $vm->cluster->agent_identifier, 'proxmox/')
                ? substr($vm->cluster->agent_identifier, strlen('proxmox/'))
                : null,
        ]);

        $known = false;
        $undecided = false;

        foreach ($scopes as $scope) {
            $job = $this->backupJobs[mb_strtolower($scope)] ?? null;
            if (! $job) {
                continue;
            }

            $known = true;

            if (($job['all'] && ! in_array((int) $vmid, $job['except'] ?? [], true)) || in_array((int) $vmid, $job['ids'], true)) {
                return true;
            }

            $undecided = $undecided || $job['pool'];
        }

        return $known && ! $undecided ? false : null;
    }

    /**
     * The services documented on the device (by hand, or from Proxmox tags
     * and Windows roles) - so the map tells what runs where.
     *
     * @return list<string>
     */
    protected function services(Server|VM $device): array
    {
        return array_values(array_filter(array_map('trim', (array) $device->services), 'filled'));
    }

    /** Reported as not running. Without a report it counts as running: nothing says otherwise. */
    protected function stopped(VM $vm): bool
    {
        $status = $vm->agent_details['status'] ?? null;

        return $status !== null && $status !== 'running';
    }

    /** The first fixed address, else "DHCP" - what the card would show first. */
    protected function address(Model $device): ?string
    {
        $ips = $device->ipAddresses;
        $fixed = $ips->first(fn (IpAddress $ip) => ! $ip->istDhcp() && filled($ip->address));

        return ($fixed ?? $ips->first())?->label();
    }

    /** Jump to the card in its list, where the user may see that list. */
    protected function url(Model $device): ?string
    {
        $class = $device::class;

        if (! array_key_exists($class, $this->lists)) {
            $type = strtolower(class_basename($class));
            $this->lists[$class] = Route::has($type.'.index') && $this->gate->allows($type.'_viewAny') ? $type : null;
        }

        return $this->lists[$class]
            ? route($this->lists[$class].'.index', [$this->customer, 'highlight' => $device->getKey()])
            : null;
    }
}
