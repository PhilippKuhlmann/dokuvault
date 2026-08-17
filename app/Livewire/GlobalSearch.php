<?php

namespace App\Livewire;

use App\Models\Accesspoint;
use App\Models\Camera;
use App\Models\Certificate;
use App\Models\Computer;
use App\Models\Concerns\HasIpAddresses;
use App\Models\DECT;
use App\Models\Domain;
use App\Models\Firewall;
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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class GlobalSearch extends Component
{
    #[Url]
    public $search;

    /**
     * Durchsuchbare Typen: Slug => [Model, Anzeigename, Permission-Prefix, Suchspalten]
     * und optional als fuenftes Element der Routen-Slug, falls der Treffer auf eine
     * andere Liste zeigt als sein eigener Schluessel (Patchfeld-Ports -> Patchfelder).
     * Die Spalten sind gegen die Migrationen verifiziert (ip vs. ip1 etc.).
     */
    protected const TYPES = [
        'server' => [Server::class, 'Server', 'server', ['name', 'serialNumber']],
        'vm' => [VM::class, 'VMs', 'vm', ['name']],
        'nas' => [NAS::class, 'NAS', 'nas', ['name', 'serialNumber']],
        'computer' => [Computer::class, 'Computer', 'computer', ['name', 'serialNumber']],
        'printer' => [Printer::class, 'Drucker', 'printer', ['name', 'serialNumber']],
        'camera' => [Camera::class, 'Kameras', 'camera', ['name', 'serialNumber']],
        'recorder' => [Recorder::class, 'Recorder', 'recorder', ['name', 'serialNumber']],
        'phone' => [Phone::class, 'Telefone', 'phone', ['serialNumber', 'mac']],
        'dect' => [DECT::class, 'DECT', 'dect', ['serialNumber', 'mac']],
        'phonesystem' => [PhoneSystem::class, 'TK-Anlagen', 'phonesystem', ['serialNumber']],
        'networkswitch' => [NetworkSwitch::class, 'Switches', 'networkswitch', ['name', 'serialNumber']],
        'accesspoint' => [Accesspoint::class, 'Accesspoints', 'accesspoint', ['name', 'serialNumber']],
        'firewall' => [Firewall::class, 'Firewalls', 'firewall', ['name', 'serialNumber']],
        'router' => [Router::class, 'Router', 'router', ['name', 'serialNumber']],
        'wifi' => [Wifi::class, 'WLAN', 'wifi', ['ssid']],
        'iotdevice' => [IoTDevice::class, 'IoT-Geräte', 'iotdevice', ['name', 'serialNumber']],
        'machine' => [Machine::class, 'Maschinen', 'machine', ['name']],
        'otherclient' => [OtherClient::class, 'Sonstige Clients', 'otherclient', ['name', 'serialNumber']],
        'ups' => [Ups::class, 'USV', 'ups', ['name', 'serialNumber']],
        'internetconnection' => [InternetConnection::class, 'Internet / WAN', 'internetconnection', ['wan_ip']],
        'domain' => [Domain::class, 'Domains', 'domain', ['name']],
        'certificate' => [Certificate::class, 'Zertifikate', 'certificate', ['name', 'common_name', 'issuer']],
        'patchpanel' => [PatchPanel::class, 'Patchfelder', 'patchpanel', ['name', 'manufacturer', 'model']],
        'patchport' => [PatchPort::class, 'Netzwerkdosen', 'patchpanel', ['outlet', 'label', 'switch_port'], 'patchpanel'],
        'rack' => [Rack::class, 'Serverschränke', 'rack', ['name', 'location']],
    ];

    /**
     * Typen, deren Tabellen so gross werden, dass nur die Praefix-Suche traegt.
     *
     * Gemessen an einem Bestand mit 10 Millionen Datensaetzen: 4 Mio
     * AD-Benutzer, 2 Mio Computer, 1,5 Mio Adressen, 1 Mio VMs. Alles andere
     * bleibt bei der Suche mitten im Wort - dort ist ein Tabellendurchlauf
     * billiger als der Verlust an Treffern.
     */
    private const MASSENHAFT = ['aduser', 'computer', 'vm', 'phone', 'camera'];

    /**
     * Die Treffer, nach Typ gruppiert.
     *
     * Als computed property statt inline in render(): So laesst sich die Suche
     * einzeln pruefen, ohne die View mitzurendern, und render() sagt wieder in
     * einer Zeile, was es tut.
     */
    #[Computed]
    public function groups()
    {
        $groups = collect();

        if (strlen((string) $this->search) >= 2) {
            $roh = addcslashes($this->search, '%_');
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

                // Wie gesucht wird, haengt an der Tabellengroesse.
                //
                // "%begriff%" kann keinen Index nutzen, MySQL liest die ganze
                // Tabelle. Bei 4 Millionen AD-Benutzern kostete das 2788 ms,
                // die Praefix-Form auf indizierter Spalte 3 ms. Bei ein paar
                // tausend Raecken oder Patchfeldern ist derselbe Scan dagegen
                // in Millisekunden erledigt.
                //
                // Deshalb Praefix nur fuer die Massentabellen. Sonst ginge
                // genau das verloren, wofuer die Suche da ist: Die Dose steht
                // als "EG 2.14" drin und wird als "2.14" gesucht, das Rack
                // heisst "Rack HH-01" und wird als "HH-01" gesucht.
                $term = in_array($slug, self::MASSENHAFT, true) ? $roh.'%' : '%'.$roh.'%';

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

        return $groups;
    }

    public function render()
    {
        return view('livewire.global-search', ['groups' => $this->groups])
            ->layout('layouts.empty', ['title' => 'Globale Suche']);
    }
}
