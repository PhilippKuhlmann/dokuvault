<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A server cluster: which servers belong together and with which technology
 * (Ceph, Replikation ...).
 *
 * Not limited to Proxmox - the kind says what it is about, so a Hyper-V or
 * database cluster fits in here as well.
 */
class Cluster extends Model
{
    use HasFactory, SoftDeletes;
    use OwnedByCustomer;
    use TracksChanges;

    /**
     * On deletion the servers only lose their membership.
     *
     * They are devices of their own and stay. The foreign-key rule
     * (nullOnDelete) only takes hold on final deletion - the cluster first
     * moves to the trash, though.
     *
     * On the model and not on the controller: deleting happens in the modal,
     * and a cluster without this rule would leave servers with a membership
     * that no longer exists.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $cluster) {
            $cluster->servers()->update(['cluster_id' => null]);
        });
    }

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class)->orderBy('name');
    }

    /**
     * The technology spelled out. An unknown key (kind later
     * removed from the configuration) returns the raw value instead of
     * vanishing - it did stand there deliberately once.
     */
    public function typeLabel(): ?string
    {
        if (blank($this->type)) {
            return null;
        }

        return __(config('custom.cluster_types')[$this->type] ?? $this->type);
    }
}
