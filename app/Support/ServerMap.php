<?php

namespace App\Support;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
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
 */
class ServerMap
{
    protected Customer $customer;

    /** Whether the user may see the guests by name, or only count them. */
    protected bool $showGuests;

    /** Model class => list route prefix or null, resolved once per request. */
    protected array $lists = [];

    public function build(Customer $customer, ?int $siteId = null): array
    {
        $this->customer = $customer;
        $this->showGuests = Gate::allows('vm_viewAny');

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
            ->with(['operatingSystem', 'ipAddresses'])
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
                'hosts' => $members->map(fn (Server $s) => $this->host($s, $byHost->get($s->id, collect())))->values()->all(),
                'loose' => $loose->isEmpty() ? null : $this->guestList($loose),
            ];
        }

        $standalone = [];

        foreach ($servers->where('site_id', $site->id) as $server) {
            if ($server->cluster_id && in_array($server->cluster_id, $clusterIds, true)) {
                continue;
            }

            $own = $byHost->get($server->id, collect());

            if ($own->isEmpty()) {
                $standalone[] = $this->host($server, $own);

                continue;
            }

            $columns[] = ['cluster' => null, 'hosts' => [$this->host($server, $own)], 'loose' => null];
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
            'unhosted' => $unhosted->isEmpty() ? null : $this->guestList($unhosted),
        ];
    }

    /** The cluster on top: its kind and what its members add up to. */
    protected function clusterNode(Cluster $cluster, Collection $members): array
    {
        $cores = $members->sum(fn (Server $s) => (int) ($s->agent_details['cores'] ?? 0));
        $memory = $members->sum(fn (Server $s) => (float) ($s->agent_details['memory_gb'] ?? 0));

        return [
            'name' => $cluster->name,
            'type' => $cluster->typeLabel() ?: __('Cluster'),
            'url' => $this->url($cluster),
            'facts' => array_values(array_filter([
                trans_choice(':count Host|:count Hosts', $members->count()),
                $cores ? trans_choice(':count Kern|:count Kerne', $cores) : null,
                $memory ? __(':menge RAM', ['menge' => AgentReportCards::gb($memory)]) : null,
            ])),
        ];
    }

    /**
     * A server with its hardware and, below it, its guests. Memory given to
     * guests against memory built in: overcommitment is a fact of the setup,
     * not a load value.
     */
    protected function host(Server $server, Collection $guests): array
    {
        $d = $server->agent_details ?? [];
        $memory = (float) ($d['memory_gb'] ?? 0);
        $assigned = (float) $guests->sum(fn (VM $vm) => $vm->agent_details['memory_gb'] ?? 0);

        return [
            'name' => $server->name,
            'type' => $guests->isEmpty() ? __('Server') : __('Host'),
            'url' => $this->url($server),
            'model' => trim($server->manufacturer.' '.$server->model) ?: null,
            'os' => $server->operatingSystem?->name ?? (isset($d['pve_version']) ? 'Proxmox VE '.$d['pve_version'] : null),
            'cpu' => $d['cpu'] ?? null,
            'facts' => array_values(array_filter([
                isset($d['cores']) ? trans_choice(':count Kern|:count Kerne', (int) $d['cores']) : null,
                $memory ? __(':menge RAM', ['menge' => AgentReportCards::gb($memory)]) : null,
            ])),
            'address' => $this->address($server),
            'assigned' => $memory && $assigned ? [
                'text' => __(':zugewiesen von :eingebaut an Gäste vergeben', [
                    'zugewiesen' => AgentReportCards::gb($assigned),
                    'eingebaut' => AgentReportCards::gb($memory),
                ]),
                'pct' => min(100, $assigned / $memory * 100),
                'over' => $assigned > $memory,
            ] : null,
            'guests' => $guests->isEmpty() ? null : $this->guestList($guests),
        ];
    }

    /**
     * Guests grouped by kind - VMs, containers, the rest - with a count, or
     * only the count for a user who may not see the VM list.
     */
    protected function guestList(Collection $guests): array
    {
        $order = ['qemu', 'lxc', 'other'];
        $labels = ['qemu' => __('VMs'), 'lxc' => __('Container'), 'other' => __('Gäste')];

        $groups = $guests
            ->groupBy(fn (VM $vm) => in_array($vm->agent_details['type'] ?? null, ['qemu', 'lxc'], true) ? $vm->agent_details['type'] : 'other')
            ->sortBy(fn ($group, string $kind) => array_search($kind, $order, true))
            ->map(fn (Collection $group, string $kind) => [
                'label' => $labels[$kind],
                'count' => $group->count(),
                'guests' => $this->showGuests ? $group->map(fn (VM $vm) => $this->guest($vm))->values()->all() : [],
            ])
            ->values()
            ->all();

        return ['count' => $guests->count(), 'visible' => $this->showGuests, 'groups' => $groups];
    }

    protected function guest(VM $vm): array
    {
        $d = $vm->agent_details ?? [];

        return [
            'name' => $vm->name,
            'url' => $this->url($vm),
            // Stopped guests stand muted - a template or a spare is part of
            // the setup, but nothing runs there.
            'stopped' => isset($d['status']) && $d['status'] !== 'running',
            // What it was given, as short chips; the operating system on a
            // line of its own - in one line with the rest it was cut off.
            'specs' => array_values(array_filter([
                isset($d['cores']) ? trans_choice(':count Kern|:count Kerne', (int) $d['cores']) : null,
                isset($d['memory_gb']) ? __(':menge RAM', ['menge' => AgentReportCards::gb((float) $d['memory_gb'])]) : null,
                empty($d['disk_gb']) ? null : __(':menge Disk', ['menge' => AgentReportCards::gb((float) $d['disk_gb'])]),
            ])),
            'os' => $vm->operatingSystem?->name,
            'address' => $this->address($vm),
        ];
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
            $this->lists[$class] = Route::has($type.'.index') && Gate::allows($type.'_viewAny') ? $type : null;
        }

        return $this->lists[$class]
            ? route($this->lists[$class].'.index', [$this->customer, 'highlight' => $device->getKey()])
            : null;
    }
}
