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

    /**
     * Der Standort kommt vom Cluster oder vom Host, sobald einer gesetzt ist.
     *
     * Eine VM laeuft dort, wo ihr Cluster oder ihr Host steht - beides
     * getrennt zu pflegen hiess, dass sie sich widersprechen koennen. Die
     * Formulare blenden das Standortfeld deshalb aus, sobald eines von beiden
     * gewaehlt ist.
     *
     * Der Cluster geht vor: Wer beides gesetzt bekommt (etwa aus einem alten
     * Formular), ist im Cluster - dort wandert die VM zwischen den Knoten,
     * der Host ist nur eine Momentaufnahme.
     *
     * Bewusst im Model und nicht im FormRequest: Das Livewire-Modal
     * (ObjektFormular) erzeugt den Request nur, um seine rules() zu lesen -
     * prepareForValidation laeuft dort nie. Hier kommen beide Wege durch,
     * ebenso der Proxmox-Agent.
     *
     * Beide werden gegen den Kunden der VM geprueft: Ein fremder Host oder
     * Cluster soll seinen Standort nicht beisteuern koennen.
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
