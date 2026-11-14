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

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        // Only on the final delete - a customer in the trash keeps its data.
        // Through Eloquent, so the files go too (see PdfExport).
        static::forceDeleting(fn ($customer) => PdfExport::where('customer_id', $customer->id)->get()->each->delete());

        static::creating(function ($customer) {
            $baseSlug = Str::slug($customer->name);

            // Keep it unique
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

    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    public function routers()
    {
        return $this->hasMany(Router::class);
    }

    public function firewalls()
    {
        return $this->hasMany(Firewall::class);
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

    /**
     * The customer's SSH keys - the same table as the logins, kept apart
     * through 'kind' (see App\Models\SshKey).
     */
    public function sshkeys()
    {
        return $this->hasMany(SshKey::class);
    }

    public function loginwebsites()
    {
        return $this->hasMany(LoginWebsite::class);
    }

    public function phonesystems()
    {
        return $this->hasMany(PhoneSystem::class);
    }

    public function sipaccounts()
    {
        return $this->hasMany(SipAccount::class);
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

    public function scanners()
    {
        return $this->hasMany(Scanner::class);
    }

    public function scantargets()
    {
        return $this->hasMany(ScanTarget::class);
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

    /**
     * This customer's agent tokens.
     *
     * The relation was missing although AgentToken has long pointed back.
     * It is needed for more than querying: the agent routes run in
     * scopeBindings(), and Laravel looks for a relation "agentTokens" on the
     * customer from the {agentToken} parameter. Without it every revoking of
     * a token ended in a 500 - the same trap as with the contact person
     * below, except that here no relation existed at all.
     */
    public function agentTokens()
    {
        return $this->hasMany(AgentToken::class);
    }

    public function agentInstallations()
    {
        return $this->hasMany(AgentInstallation::class);
    }

    public function networkScanFindings()
    {
        return $this->hasMany(NetworkScanFinding::class);
    }

    /**
     * Resolve the child binding for contact persons by hand.
     *
     * The routes run in scopeBindings(). From the {contactperson} parameter
     * Laravel builds the plural "contactpeople" and looks for a relation of
     * that name - which is called contactpersons here. Every edit of a
     * contact person therefore ended in an error, while list and create
     * worked.
     *
     * No second relation name as a shortcut: the same relation under two
     * names has cost cleanup work here once already, and a test has held it
     * down since.
     */
    public function resolveChildRouteBinding($childType, $value, $field = null)
    {
        if ($childType === 'contactperson') {
            return $this->contactpersons()
                ->where($field ?? (new ContactPerson)->getRouteKeyName(), $value)
                ->firstOrFail();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
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

    public function networkshares()
    {
        return $this->hasMany(NetworkShare::class);
    }

    public function checklists()
    {
        return $this->hasMany(Checklist::class);
    }

    public function checklistOptions()
    {
        return $this->hasMany(ChecklistOption::class);
    }

    /** Standard checklist steps this customer has switched off. */
    public function hiddenChecklistItems()
    {
        return $this->belongsToMany(ChecklistTemplateItem::class, 'checklist_template_item_hidden');
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
