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
        'nas' => NAS::class,
    ];

    /** Shown behind the name in the selects. */
    private const LABELS = ['server' => 'Server', 'vm' => 'VM', 'nas' => 'NAS'];

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
     * The machines of a customer as key => label, for the selects. Servers
     * and VMs by default; a form field may allow more (a network drive also
     * sits on a NAS).
     *
     * @param  array<int, string>  $types  prefixes from TYPES
     * @return array<string, string>
     */
    public static function options(int $customerId, array $types = ['server', 'vm']): array
    {
        $optionen = [];

        foreach (array_intersect_key(self::TYPES, array_flip($types)) as $prefix => $klasse) {
            foreach ($klasse::where('customer_id', $customerId)->orderBy('name')->get() as $host) {
                $optionen[$prefix.':'.$host->id] = $host->name.' ('.self::LABELS[$prefix].')';
            }
        }

        return $optionen;
    }
}
