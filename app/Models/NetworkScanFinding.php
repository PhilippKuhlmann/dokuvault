<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A device the network scan found and nobody decided on yet - or one that
 * was ignored (see the migration for the why).
 *
 * Taken over or linked, the row is deleted: the device then carries the
 * identifier and later scans update it directly. Ignored, it stays, so the
 * scan does not offer it again.
 */
class NetworkScanFinding extends Model
{
    use OwnedByCustomer;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Key => [model, label] of what the scan recognises, in the order the
     * list shows them. The keys are what the agent sends as "type".
     */
    public const TYPES = [
        'printer' => [Printer::class, 'Drucker'],
        'ups' => [Ups::class, 'USV'],
        'switch' => [NetworkSwitch::class, 'Switch'],
        'accesspoint' => [Accesspoint::class, 'Accesspoint'],
        'nas' => [NAS::class, 'NAS'],
        'camera' => [Camera::class, 'Kamera'],
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function agentInstallation()
    {
        return $this->belongsTo(AgentInstallation::class);
    }

    public function network()
    {
        return $this->belongsTo(Network::class);
    }

    /** @return class-string<Printer|Ups|NetworkSwitch|Accesspoint|NAS|Camera> */
    public function modelClass(): string
    {
        return self::TYPES[$this->type][0];
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type][1]);
    }

    /** What the device is called when taken over: its SNMP name, else model and address. */
    public function suggestedName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        return trim(($this->model ?: $this->typeLabel()).($this->ip ? ' ('.$this->ip.')' : ''));
    }

    /**
     * The site a taken-over device belongs to: that of the scanned network,
     * else that of the agent's token.
     */
    public function suggestedSiteId(): ?int
    {
        return $this->network?->site_id ?? $this->agentInstallation?->agentToken?->site_id;
    }

    /**
     * Devices of this kind documented by hand that may be this one: same
     * serial number or same address, not yet tied to a scan. Offered first
     * for "Mit vorhandenem verknüpfen" instead of a duplicate.
     *
     * @return Collection<int, Model>
     */
    public function linkCandidates()
    {
        $klasse = $this->modelClass();

        $nachSerie = filled($this->serial)
            ? $klasse::forCustomer($this->customer_id)->whereNull('agent_identifier')
                ->where('serialNumber', $this->serial)->get()
            : collect();

        $nachAdresse = filled($this->ip)
            ? $klasse::forCustomer($this->customer_id)->whereNull('agent_identifier')
                ->whereHas('ipAddresses', fn ($q) => $q->where('address', $this->ip))->get()
            : collect();

        return $nachSerie->merge($nachAdresse)->unique('id')->values();
    }

    /**
     * All documented devices of this kind not yet tied to a scan - the
     * choice when linking, with the likely ones first.
     *
     * @return Collection<int, Model>
     */
    public function linkChoices()
    {
        $kandidaten = $this->linkCandidates();

        $uebrige = $this->modelClass()::forCustomer($this->customer_id)
            ->whereNull('agent_identifier')
            ->whereNotIn('id', $kandidaten->pluck('id'))
            ->orderBy('name')
            ->get();

        return $kandidaten->concat($uebrige);
    }
}
