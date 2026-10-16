<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A network drive: the server, VM or NAS it sits on, path, drive letter and
 * the AD groups that grant read and write access. The onboarding form offers
 * the drives switched on with in_onboarding_form (set in the checklist
 * settings); the checklist then names the group to assign.
 */
class NetworkShare extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'sensitive' => 'boolean',
        'in_onboarding_form' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** The server, VM or NAS that holds the share. */
    public function host()
    {
        return $this->morphTo();
    }

    /**
     * The form's 'host' field ("server:5"), see ADDomainHost. One machine,
     * the role is not needed - the interface is the AD domain's.
     *
     * @return array<int, string>
     */
    public function hostKeys(string $role): array
    {
        return $this->host ? [ADDomainHost::key($this->host)] : [];
    }

    /** Sets the machine from a form key; only machines of this customer. */
    public function syncHosts(string $role, array $keys): void
    {
        [$prefix, $id] = array_pad(explode(':', (string) (array_values(array_filter($keys))[0] ?? ''), 2), 2, null);
        $class = ADDomainHost::TYPES[$prefix] ?? null;
        $host = $class ? $class::where('customer_id', $this->customer_id)->find($id) : null;

        $this->update(['host_type' => $host ? $class : null, 'host_id' => $host?->id]);
        $this->unsetRelation('host');
    }

    public function readGroup()
    {
        return $this->belongsTo(ADGroup::class, 'ad_group_read_id');
    }

    public function writeGroup()
    {
        return $this->belongsTo(ADGroup::class, 'ad_group_write_id');
    }

    /** "s:" and "s" both become "S". */
    protected function driveLetter(): Attribute
    {
        return Attribute::make(set: fn ($value) => ($letter = strtoupper(rtrim(trim((string) $value), ':'))) !== '' ? $letter : null);
    }

    /** "S: Gemeinsam" with a drive letter, the plain name without. */
    public function label(): string
    {
        return $this->drive_letter ? "{$this->drive_letter}: {$this->name}" : $this->name;
    }

    /** The AD group for a level: 'r' read, 'rw' write. */
    public function groupFor(string $level): ?ADGroup
    {
        return $level === 'rw' ? $this->writeGroup : $this->readGroup;
    }
}
