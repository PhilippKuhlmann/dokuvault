<?php

namespace App\Support;

use App\Models\Accesspoint;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Camera;
use App\Models\Certificate;
use App\Models\Checklist;
use App\Models\Computer;
use App\Models\Concerns\HasIpAddresses;
use App\Models\DECT;
use App\Models\Domain;
use App\Models\Firewall;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\Machine;
use App\Models\NAS;
use App\Models\NetworkShare;
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
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Models\Server;
use App\Models\Ups;
use App\Models\User;
use App\Models\VM;
use App\Models\Wifi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Search across every customer - for the page and for the app.
 *
 * This used to sit entirely in App\Livewire\GlobalSearch. The app needs the same
 * Antwort auf dieselbe Frage; zwei Kopien haetten bald zwei Meinungen darueber,
 * wer was sehen darf.
 */
class Search
{
    /**
     * Searchable kinds: slug => [model, display name, permission prefix, search columns]
     * and optionally, as a fifth element, the route slug if the hit points at a
     * different list than its own key (patch panel ports -> patch panels).
     * The columns are verified against the migrations (ip vs. ip1 etc.).
     */
    public const TYPES = [
        'server' => [Server::class, 'Server', 'server', ['name', 'serialNumber']],
        'vm' => [VM::class, 'VMs', 'vm', ['name']],
        'nas' => [NAS::class, 'NAS', 'nas', ['name', 'serialNumber']],
        'networkshare' => [NetworkShare::class, 'Netzlaufwerke', 'networkshare', ['name', 'path']],
        'aduser' => [ADUser::class, 'AD-Benutzer', 'aduser', ['username', 'firstName', 'lastName', 'email']],
        'adgroup' => [ADGroup::class, 'AD-Gruppen', 'adgroup', ['name']],
        'checklist' => [Checklist::class, 'Checklisten', 'checklist', ['first_name', 'last_name', 'username', 'email']],
        'computer' => [Computer::class, 'Computer', 'computer', ['name', 'serialNumber']],
        'printer' => [Printer::class, 'Drucker', 'printer', ['name', 'serialNumber']],
        'scanner' => [Scanner::class, 'Scanner', 'scanner', ['name', 'serialNumber']],
        'scantarget' => [ScanTarget::class, 'Scan-Ziele', 'scantarget', ['name', 'target']],
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
     * Kinds whose tables grow so large that only the prefix search carries.
     *
     * Measured on a stock of 10 million records: 4 million AD users, the rest
     * AD-Benutzer, 2 Mio Computer, 1,5 Mio Adressen, 1 Mio VMs. Alles andere
     * stays with the search in mid-word - a table scan is cheaper there than
     * the loss of hits.
     */
    private const MASSENHAFT = ['aduser', 'computer', 'vm', 'phone', 'camera'];

    /**
     * How many hits per object kind are shown at most.
     *
     * The search runs across nearly forty kinds; without a cap a query for
     * "a" would be a listing of the entire stock. Whoever is looking types on
     * anyway - the note under the group says there is more.
     */
    public const JE_ART = 20;

    /**
     * Types whose hit opens the entry itself instead of jumping into a list.
     * A checklist is worked through on its own page; its list only shows
     * the open ones and has no row to highlight.
     */
    public const ZUR_DETAILSEITE = ['checklist'];

    /**
     * The hits, grouped by kind.
     *
     * Per group: typ (its own key), slug (the list the hit points at),
     * label, results, weitere, highlightable.
     */
    public function groups(User $user, string $roh): Collection
    {
        $groups = collect();

        // Raw: escaping the LIKE placeholders is done by whereEnthaelt.
        // Escaped twice, "srv_01" would become a term searching for the
        // backslash itself - and finding nothing at all.
        if (strlen($roh) < 2) {
            return $groups;
        }

        foreach (self::TYPES as $slug => $entry) {
            [$class, $label, $permission, $columns] = $entry;
            $routeSlug = $entry[4] ?? $slug;
            if (! Gate::forUser($user)->allows($permission.'_viewAny')) {
                continue;
            }

            $query = $class::query()->with('customer');

            // Customer users see only objects of their own customer
            if ($user->customer_id) {
                $query->where('customer_id', $user->customer_id);
            }

            // The additionally documented addresses count too. Since the server
            // forms no longer carry ip1/ip2, IP addresses of new devices live
            // exclusively there - without this the search would no longer find
            // them.
            $hatIpBlock = in_array(HasIpAddresses::class, class_uses_recursive($class), true);

            // How we search depends on the size of the table.
            //
            // "%term%" cannot use an index, MySQL reads the whole table. At 4
            // million AD users that cost 2788 ms, the prefix form on an indexed
            // column 3 ms. On a few thousand racks or patch panels the same
            // scan is done in milliseconds, though.
            // in Millisekunden erledigt.
            //
            // Hence prefix only for the bulk tables. Otherwise exactly what the
            // search is for would be lost: the outlet is documented as
            // "EG 2.14" and searched for as "2.14", the rack is called
            // "Rack HH-01" and searched for as "HH-01".
            $nurAnfang = in_array($slug, self::MASSENHAFT, true);

            // whereBeginntMit / whereEnthaelt instead of like: without escaping
            // an underscore in the term stood for any character - "SRV_01" also
            // found "SRV101" - and a percent sign for the entire stock.
            // percent sign for the entire stock.
            $query->where(function ($q) use ($columns, $roh, $nurAnfang, $hatIpBlock) {
                $nurAnfang
                    ? $q->whereBeginntMit($columns, $roh)
                    : $q->whereEnthaelt($columns, $roh);

                if ($hatIpBlock) {
                    $q->orWhereHas('ipAddresses', fn ($ip) => $nurAnfang
                        ? $ip->whereBeginntMit('address', $roh)
                        : $ip->whereEnthaelt('address', $roh));
                }
            });

            // Fetch one more than is shown: that settles whether there are more
            // without counting a second time - the same approach as in the
            // customer search. Before, it was cut off silently at twenty, and
            // whoever looked for the twenty-first server took it to be
            // missing.
            // vorhanden.
            $treffer = $query->limit(self::JE_ART + 1)->get();

            if ($treffer->isNotEmpty()) {
                $groups->push([
                    'typ' => $slug,
                    'slug' => $routeSlug,
                    'label' => $label,
                    'results' => $treffer->take(self::JE_ART),
                    'weitere' => max(0, $treffer->count() - self::JE_ART),
                    // Only highlightable when the match points at its own list.
                    // Patch-panel ports land on the patch-panel list - there their
                    // id matches no row, so a ?highlight would point nowhere.
                    'highlightable' => $routeSlug === $slug,
                    'detailseite' => in_array($slug, self::ZUR_DETAILSEITE, true),
                ]);
            }
        }

        return $groups;
    }
}
