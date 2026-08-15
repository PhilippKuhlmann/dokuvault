<?php

namespace App\Http\Controllers;

use App\Models\Accesspoint;
use App\Models\ADUser;
use App\Models\Camera;
use App\Models\Certificate;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\LicenseAccess;
use App\Models\LicenseSoftware;
use App\Models\LicenseWindows;
use App\Models\NAS;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\Phone;
use App\Models\Printer;
use App\Models\Role;
use App\Models\Router;
use App\Models\Server;
use App\Models\User;
use App\Models\VM;
use App\Models\Wifi;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['isAdmin']);
    }

    public function index()
    {
        $tiles = [
            ['label' => 'Benutzer', 'icon' => 'svg.user',     'count' => User::count(),     'route' => route('admin.user.index')],
            ['label' => 'Kunden',   'icon' => 'svg.office',   'count' => Customer::count(), 'route' => route('admin.customer.index')],
            ['label' => 'Rollen',   'icon' => 'svg.group',    'count' => Role::count(),     'route' => route('admin.role.index')],
            ['label' => 'Aktivitäten', 'icon' => 'svg.document', 'count' => Activity::count(), 'route' => route('admin.activity.index')],
        ];

        // Globale Ablauf-Übersicht (<= 60 Tage, inkl. bereits abgelaufen) über alle Kunden
        $limit = now()->addDays(60);
        $expiring = collect();
        foreach (LicenseSoftware::whereNotNull('end_date')->whereDate('end_date', '<=', $limit)->with('customer')->get() as $l) {
            $expiring->push(['type' => 'Lizenz', 'name' => $l->name, 'date' => $l->end_date, 'customer' => $l->customer, 'route' => $l->customer ? route('licensesoftware.index', $l->customer) : null]);
        }
        foreach (Certificate::whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $limit)->with('customer')->get() as $c) {
            $expiring->push(['type' => 'Zertifikat', 'name' => $c->name, 'date' => $c->expiry_date, 'customer' => $c->customer, 'route' => $c->customer ? route('certificate.index', $c->customer) : null]);
        }
        foreach (Domain::whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $limit)->with('customer')->get() as $d) {
            $expiring->push(['type' => 'Domain', 'name' => $d->name, 'date' => $d->expiry_date, 'customer' => $d->customer, 'route' => $d->customer ? route('domain.index', $d->customer) : null]);
        }
        // Betriebssysteme haben keinen Kunden - hier zaehlt, wie viele Geraete
        // betroffen sind. Ohne Geraete darauf ist ein EOL-System kein Problem.
        foreach (OperatingSystem::whereNotNull('eol_date')->whereDate('eol_date', '<=', $limit)
            ->withCount(['servers', 'vms'])->get() as $os) {
            $betroffen = $os->servers_count + $os->vms_count;
            if ($betroffen === 0) {
                continue;
            }
            $expiring->push([
                'type' => 'Betriebssystem',
                'name' => $os->name.' ('.$betroffen.' Systeme)',
                'date' => $os->eol_date,
                'customer' => null,
                'route' => route('admin.eol.index'),
            ]);
        }

        $expiring = $expiring->sortBy('date')->take(12)->values();

        // Globale Inventar-Statistik (über alle Kunden)
        $inventory = [
            ['label' => 'Server',      'icon' => 'svg.servers',  'count' => Server::count()],
            ['label' => 'VMs',         'icon' => 'svg.server',   'count' => VM::count()],
            ['label' => 'Computer',    'icon' => 'svg.computer', 'count' => Computer::count()],
            ['label' => 'NAS',         'icon' => 'svg.db',       'count' => NAS::count()],
            ['label' => 'Netzwerk',    'icon' => 'svg.wifi',     'count' => NetworkSwitch::count() + Accesspoint::count() + Router::count()],
            ['label' => 'WLAN',        'icon' => 'svg.signal',   'count' => Wifi::count()],
            ['label' => 'Drucker',     'icon' => 'svg.printer',  'count' => Printer::count()],
            ['label' => 'Kameras',     'icon' => 'svg.cam',      'count' => Camera::count()],
            ['label' => 'Telefone',    'icon' => 'svg.phone',    'count' => Phone::count()],
            ['label' => 'AD-User',     'icon' => 'svg.user',     'count' => ADUser::count()],
            ['label' => 'Lizenzen',    'icon' => 'svg.document', 'count' => LicenseSoftware::count() + LicenseWindows::count() + LicenseAccess::count()],
            ['label' => 'Zertifikate', 'icon' => 'svg.document', 'count' => Certificate::count()],
        ];

        // Letzte Aktivitäten
        $activities = Activity::with('causer')->latest()->limit(10)->get();

        // Top-Kunden nach dokumentierten Geräten
        $topCustomers = Customer::withCount(['servers', 'computers', 'vms', 'nas', 'printers', 'cameras', 'networkswitches', 'accesspoints', 'routers', 'wifis', 'phones'])
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'count' => $c->servers_count + $c->computers_count + $c->vms_count + $c->nas_count + $c->printers_count
                    + $c->cameras_count + $c->networkswitches_count + $c->accesspoints_count + $c->routers_count + $c->wifis_count + $c->phones_count,
            ])
            ->sortByDesc('count')->take(6)->values();

        // Aktivitäts-Verlauf der letzten 14 Tage
        $byDay = Activity::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->get()->groupBy(fn ($a) => $a->created_at->format('Y-m-d'))->map->count();
        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $chart[] = ['label' => $day->format('d.m.'), 'count' => $byDay[$day->format('Y-m-d')] ?? 0];
        }

        return view('admin.index', compact('tiles', 'expiring', 'inventory', 'activities', 'topCustomers', 'chart'));
    }

    public function apitoken()
    {
        $user = auth()->user();
        $token = $user->createToken('optin');

        return ['token' => $token->plainTextToken];
    }
}
