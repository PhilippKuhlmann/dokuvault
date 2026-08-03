<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            $baseSlug = Str::slug($customer->name);

            // Eindeutigkeit sicherstellen
            $slug = $baseSlug;
            $counter = 1;
            while (static::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            $customer->slug = $slug;
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }

    public function patchpanels()
    {
        return $this->hasMany(PatchPanel::class);
    }

    public function securepointutms()
    {
        return $this->hasMany(SecurepointUTM::class);
    }

    public function routers()
    {
        return $this->hasMany(Router::class);
    }

    public function securepointumas()
    {
        return $this->hasMany(SecurepointUMA::class);
    }

    public function networks()
    {
        return $this->hasMany(Network::class);
    }

    public function networkswitches()
    {
        return $this->hasMany(NetworkSwitch::class);
    }

    public function accesspoints()
    {
        return $this->hasMany(Accesspoint::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function vms()
    {
        return $this->hasMany(VM::class);
    }

    public function nas()
    {
        return $this->hasMany(NAS::class);
    }

    public function addomains()
    {
        return $this->hasMany(ADDomain::class);
    }

    public function adusers()
    {
        return $this->hasMany(ADUser::class);
    }

    public function adgroups()
    {
        return $this->hasMany(ADGroup::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function logingenerals()
    {
        return $this->hasMany(LoginGeneral::class);
    }

    public function loginwebsites()
    {
        return $this->hasMany(LoginWebsite::class);
    }

    public function loginnas()
    {
        return $this->hasMany(LoginNAS::class);
    }

    public function loginrecorders()
    {
        return $this->hasMany(LoginRecorder::class);
    }

    public function phonesystems()
    {
        return $this->hasMany(PhoneSystem::class);
    }

    public function phones()
    {
        return $this->hasMany(Phone::class);
    }

    public function dects()
    {
        return $this->hasMany(DECT::class);
    }

    public function mailboxes()
    {
        return $this->hasMany(Mailbox::class);
    }

    public function wifis()
    {
        return $this->hasMany(Wifi::class);
    }

    public function computers()
    {
        return $this->hasMany(Computer::class);
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }

    public function ftpservers()
    {
        return $this->hasMany(FTPServer::class);
    }

    public function dyndns()
    {
        return $this->hasMany(DynDNS::class);
    }

    public function recorders()
    {
        return $this->hasMany(Recorder::class);
    }

    public function cameras()
    {
        return $this->hasMany(Camera::class);
    }

    public function contactpersons()
    {
        return $this->hasMany(ContactPerson::class);
    }

    public function licensewindows()
    {
        return $this->hasMany(LicenseWindows::class);
    }

    public function licensesoftware()
    {
        return $this->hasMany(LicenseSoftware::class);
    }

    public function licenseaccesses()
    {
        return $this->hasMany(LicenseAccess::class);
    }

    public function iotdevices()
    {
        return $this->hasMany(IoTDevice::class);
    }

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function otherclients()
    {
        return $this->hasMany(OtherClient::class);
    }

    public function ups()
    {
        return $this->hasMany(Ups::class);
    }

    public function internetconnections()
    {
        return $this->hasMany(InternetConnection::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }
}
