<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class ADDomain extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ad_domains';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function hosts()
    {
        return $this->hasMany(ADDomainHost::class, 'ad_domain_id');
    }

    /**
     * Form keys ("server:5") of the machines in one role.
     *
     * @return array<int, string>
     */
    public function hostKeys(string $role): array
    {
        return $this->hostsIn($role)->map(fn ($host) => ADDomainHost::key($host))->values()->all();
    }

    /** Names for list and PDF: "SRV-DC01, VM-DC02". */
    public function hostNames(string $role): ?string
    {
        return $this->hostsIn($role)->pluck('name')->implode(', ') ?: null;
    }

    /**
     * Replace the machines of one role with the given form keys.
     *
     * Only machines of this customer: the keys come from the browser.
     *
     * @param  array<int, string>  $keys
     */
    public function syncHosts(string $role, array $keys): void
    {
        $this->hosts()->where('role', $role)->delete();

        foreach (array_unique(array_filter($keys)) as $key) {
            [$prefix, $id] = array_pad(explode(':', $key, 2), 2, null);
            $klasse = ADDomainHost::TYPES[$prefix] ?? null;
            $host = $klasse ? $klasse::where('customer_id', $this->customer_id)->find($id) : null;

            if ($host) {
                $this->hosts()->create(['role' => $role, 'host_type' => $klasse, 'host_id' => $host->id]);
            }
        }

        $this->unsetRelation('hosts');
    }

    protected function hostsIn(string $role)
    {
        return $this->hosts->where('role', $role)->map->host->filter()->sortBy('name');
    }

    /** Spelled out for list and PDF, not the raw key ('2012R2'). */
    public function functionalLevelLabel(): ?string
    {
        return config('custom.ad_functional_levels')[$this->functional_level ?? ''] ?? null;
    }

    /**
     * Das DSRM-Kennwort verschluesselt, wie die uebrigen Geraetekennwoerter.
     *
     * Hier stand vorher ein Accessor namens password(). Eine Spalte dieses
     * Namens gibt es in ad_domains nicht - er lief ins Leere, und das Kennwort
     * stand im Klartext in der Datenbank. Der Methodenname muss zur Spalte
     * passen, sonst wiederholt sich genau das.
     */
    protected function dsrmpassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => filled($value) ? Crypt::decryptString($value) : null,
            // Leer bleibt leer statt zu einem Chiffrat ueber nichts zu werden.
            // Kein null: Die Spalte ist NOT NULL, und das Feld ist Pflicht -
            // leer kommt hier ohnehin nur ueber den Seeder oder einen Test an.
            set: fn ($value) => filled($value) ? Crypt::encryptString($value) : '',
        );
    }
}
