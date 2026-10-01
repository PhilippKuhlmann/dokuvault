<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Which server or VM runs a service of an AD domain.
 *
 * No SoftDeletes: like CredentialLink this is a reference, not documentation.
 * A machine in the trash simply drops out of host() and thus of every list.
 */
class ADDomainHost extends Model
{
    protected $table = 'ad_domain_hosts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Key prefix in the form ("server:5") => model. */
    public const TYPES = [
        'server' => Server::class,
        'vm' => VM::class,
    ];

    public function host()
    {
        return $this->morphTo();
    }

    /** "server:5" - how the form addresses a machine across both tables. */
    public static function key(Model $host): string
    {
        return array_search($host::class, self::TYPES, true).':'.$host->getKey();
    }

    /**
     * All servers and VMs of a customer as key => label, for the selects.
     *
     * @return array<string, string>
     */
    public static function options(int $customerId): array
    {
        $optionen = [];

        foreach (self::TYPES as $prefix => $klasse) {
            $typ = $prefix === 'vm' ? 'VM' : 'Server';

            foreach ($klasse::where('customer_id', $customerId)->orderBy('name')->get() as $host) {
                $optionen[$prefix.':'.$host->id] = $host->name.' ('.$typ.')';
            }
        }

        return $optionen;
    }
}
