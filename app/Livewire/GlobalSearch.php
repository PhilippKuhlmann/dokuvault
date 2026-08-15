<?php

namespace App\Livewire;

use App\Models\Accesspoint;
use App\Models\Camera;
use App\Models\Certificate;
use App\Models\Computer;
use App\Models\Concerns\HasIpAddresses;
use App\Models\DECT;
use App\Models\Domain;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\Machine;
use App\Models\NAS;
use App\Models\NetworkSwitch;
use App\Models\OtherClient;
use App\Models\PatchPanel;
use App\Models\PatchPort;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Rack;
use App\Models\Recorder;
use App\Models\Router;
use App\Models\Server;
use App\Models\Ups;
use App\Models\VM;
use App\Models\Wifi;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class GlobalSearch extends Component
{
    public $search;

    protected $queryString = ['search'];

    /**
     * Durchsuchbare Typen: Slug => [Model, Anzeigename, Permission-Prefix, Suchspalten]
     * und optional als fuenftes Element der Routen-Slug, falls der Treffer auf eine
     * andere Liste zeigt als sein eigener Schluessel (Patchfeld-Ports -> Patchfelder).
     * Die Spalten sind gegen die Migrationen verifiziert (ip vs. ip1 etc.).
     */
    protected const TYPES = [
        'server' => [Server::class, 'Server', 'server', ['name', 'ip1', 'ip2', 'serialNumber']],
        'vm' => [VM::class, 'VMs', 'vm', ['name', 'ip1', 'ip2']],
        'nas' => [NAS::class, 'NAS', 'nas', ['name', 'ip1', 'ip2', 'serialNumber']],
        'computer' => [Computer::class, 'Computer', 'computer', ['name', 'ip', 'serialNumber']],
        'printer' => [Printer::class, 'Drucker', 'printer', ['name', 'ip', 'serialNumber']],
        'camera' => [Camera::class, 'Kameras', 'camera', ['name', 'ip', 'serialNumber']],
        'recorder' => [Recorder::class, 'Recorder', 'recorder', ['name', 'ip', 'serialNumber']],
        'phone' => [Phone::class, 'Telefone', 'phone', ['ip', 'serialNumber', 'mac']],
        'dect' => [DECT::class, 'DECT', 'dect', ['ip', 'serialNumber', 'mac']],
        'phonesystem' => [PhoneSystem::class, 'TK-Anlagen', 'phonesystem', ['ip1', 'serialNumber']],
        'networkswitch' => [NetworkSwitch::class, 'Switches', 'networkswitch', ['name', 'ip', 'serialNumber']],
        'accesspoint' => [Accesspoint::class, 'Accesspoints', 'accesspoint', ['name', 'ip', 'serialNumber']],
        'router' => [Router::class, 'Router', 'router', ['name', 'ip', 'serialNumber']],
        'wifi' => [Wifi::class, 'WLAN', 'wifi', ['ssid']],
        'iotdevice' => [IoTDevice::class, 'IoT-Geräte', 'iotdevice', ['name', 'ip', 'serialNumber']],
        'machine' => [Machine::class, 'Maschinen', 'machine', ['name', 'ip']],
        'otherclient' => [OtherClient::class, 'Sonstige Clients', 'otherclient', ['name', 'ip', 'serialNumber']],
        'ups' => [Ups::class, 'USV', 'ups', ['name', 'ip', 'serialNumber']],
        'internetconnection' => [InternetConnection::class, 'Internet / WAN', 'internetconnection', ['wan_ip']],
        'domain' => [Domain::class, 'Domains', 'domain', ['name']],
        'certificate' => [Certificate::class, 'Zertifikate', 'certificate', ['name', 'common_name', 'issuer']],
        'patchpanel' => [PatchPanel::class, 'Patchfelder', 'patchpanel', ['name', 'manufacturer', 'model']],
        'patchport' => [PatchPort::class, 'Netzwerkdosen', 'patchpanel', ['outlet', 'label', 'switch_port'], 'patchpanel'],
        'rack' => [Rack::class, 'Serverschränke', 'rack', ['name', 'location']],
    ];

    public function render()
    {
        $groups = collect();

        if (strlen((string) $this->search) >= 2) {
            $term = '%'.addcslashes($this->search, '%_').'%';
            $user = auth()->user();

            foreach (self::TYPES as $slug => $entry) {
                [$class, $label, $permission, $columns] = $entry;
                $routeSlug = $entry[4] ?? $slug;
                if (! Gate::allows($permission.'_viewAny')) {
                    continue;
                }

                $query = $class::query()->with('customer');

                // Kunden-Nutzer sehen nur Objekte des eigenen Kunden
                if ($user->customer_id) {
                    $query->where('customer_id', $user->customer_id);
                }

                // Die zusaetzlich dokumentierten Adressen zaehlen mit. Seit die
                // Server-Formulare ip1/ip2 nicht mehr fuehren, stehen IP-Adressen
                // neuer Geraete ausschliesslich dort - ohne das hier faende die
                // Suche sie nicht mehr.
                $hatIpBlock = in_array(HasIpAddresses::class, class_uses_recursive($class), true);

                $query->where(function ($q) use ($columns, $term, $hatIpBlock) {
                    foreach ($columns as $column) {
                        $q->orWhere($column, 'like', $term);
                    }

                    if ($hatIpBlock) {
                        $q->orWhereHas('ipAddresses', fn ($ip) => $ip->where('address', 'like', $term));
                    }
                });

                $results = $query->limit(20)->get();

                if ($results->isNotEmpty()) {
                    $groups->push([
                        'slug' => $routeSlug,
                        'label' => $label,
                        'results' => $results,
                    ]);
                }
            }
        }

        return view('livewire.global-search', ['groups' => $groups])
            ->layout('layouts.empty', ['title' => 'Globale Suche']);
    }
}
