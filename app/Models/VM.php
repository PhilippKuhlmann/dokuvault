<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class VM extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use TracksChanges;

    protected $table = 'vms';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'agent_details' => 'array',
        'agent_reported_at' => 'datetime',
    ];

    /**
     * The site comes from the cluster or from the host as soon as one is set.
     *
     * A VM runs where its cluster or its host stands - keeping both
     * separately meant they could contradict each other. The forms therefore
     * hide the site field as soon as one of the two is chosen.
     *
     *
     * The cluster takes precedence: whoever ends up with both set (from an
     * old form, say) is in the cluster - there the VM moves between nodes,
     * the host is only a snapshot.
     *
     * Deliberately in the model and not in the FormRequest: the Livewire
     * modal (ObjectModal) creates the request only to read its rules() -
     * prepareForValidation never runs there. Here both ways come through,
     * and the Proxmox agent as well.
     *
     * Both are checked against the VM's customer: a foreign host or cluster
     * must not be able to contribute its site.
     */
    protected static function booted(): void
    {
        static::saving(function (self $vm) {
            $standort = match (true) {
                filled($vm->cluster_id) => Cluster::where('id', $vm->cluster_id)
                    ->where('customer_id', $vm->customer_id)->value('site_id'),
                filled($vm->server_id) => Server::where('id', $vm->server_id)
                    ->where('customer_id', $vm->customer_id)->value('site_id'),
                default => null,
            };

            if ($standort) {
                $vm->site_id = $standort;
            }
        });
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    protected function services(): Attribute
    {
        return new Attribute(
            get: fn ($value) => explode(',', $value),
        );
    }

    protected function remotePassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    public function operatingSystem()
    {
        return $this->belongsTo(OperatingSystem::class);
    }

    public function host()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
