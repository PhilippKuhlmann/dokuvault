<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Jobs\KundenPdfErzeugen;
use App\Models\Certificate;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\LicenseSoftware;
use App\Models\PdfExport;
use App\Models\Role;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(Customer $customer)
    {
        $this->middleware(['auth', 'isCustomer']);
    }

    public function search()
    {

        if (auth()->user()->role_id === Role::IS_ADMIN) {
            return redirect('/admin');
        }

        session()->put('site', 'all');

        $customers = null;

        if (request('search')) {
            $customers = Customer::where('name', 'like', '%'.request('search').'%')->get();
            if ($customers->isempty()) {
                $customers = null;
            }
        }

        return view('customer.search', [
            'customers' => $customers,
        ]);
    }

    public function dashboard(Customer $customer)
    {
        $sites = Site::where('customer_id', $customer->id)->get();
        $contactpersons = ContactPerson::where('customer_id', $customer->id)->get();

        // Inventar-Zähler (in einer Abfrage via loadCount)
        $customer->loadCount([
            'internetconnections', 'securepointutms', 'routers', 'networkswitches',
            'accesspoints', 'networks', 'wifis', 'racks', 'patchpanels',
            'servers', 'vms', 'nas', 'computers', 'printers', 'cameras',
            'phones', 'adusers',
        ]);

        $tiles = [
            // Von aussen nach innen, so wie man vor Ort danach sucht: erst
            // der Anschluss und was daran haengt, dann die Server, dann die
            // Arbeitsplaetze. Firewall, Router, Switches, Accesspoints,
            // Schraenke und Patchfelder fehlten hier ganz - man konnte sie
            // dokumentieren, sah sie aber in der Uebersicht nie wieder.
            ['label' => 'Internet / WAN', 'icon' => 'svg.link',     'count' => $customer->internetconnections_count, 'route' => route('internetconnection.index', $customer), 'can' => 'internetconnection_viewAny'],
            ['label' => 'Securepoint UTM', 'icon' => 'svg.fire',     'count' => $customer->securepointutms_count,     'route' => route('securepointutm.index', $customer),     'can' => 'securepointutm_viewAny'],
            ['label' => 'Router',         'icon' => 'svg.wifi',     'count' => $customer->routers_count,             'route' => route('router.index', $customer),             'can' => 'router_viewAny'],
            ['label' => 'Switches',       'icon' => 'svg.group',    'count' => $customer->networkswitches_count,     'route' => route('networkswitch.index', $customer),      'can' => 'networkswitch_viewAny'],
            ['label' => 'Accesspoints',   'icon' => 'svg.signal',   'count' => $customer->accesspoints_count,        'route' => route('accesspoint.index', $customer),        'can' => 'accesspoint_viewAny'],
            ['label' => 'Netzwerke',      'icon' => 'svg.wifi',     'count' => $customer->networks_count,            'route' => route('network.index', $customer),            'can' => 'network_viewAny'],
            ['label' => 'WLAN',           'icon' => 'svg.signal',   'count' => $customer->wifis_count,               'route' => route('wifi.index', $customer),               'can' => 'wifi_viewAny'],
            ['label' => 'Serverschränke', 'icon' => 'svg.folder',   'count' => $customer->racks_count,               'route' => route('rack.index', $customer),               'can' => 'rack_viewAny'],
            ['label' => 'Patchfelder',    'icon' => 'svg.link',     'count' => $customer->patchpanels_count,         'route' => route('patchpanel.index', $customer),         'can' => 'patchpanel_viewAny'],
            ['label' => 'Server',         'icon' => 'svg.servers',  'count' => $customer->servers_count,             'route' => route('server.index', $customer),             'can' => 'server_viewAny'],
            ['label' => 'VMs',            'icon' => 'svg.server',   'count' => $customer->vms_count,                 'route' => route('vm.index', $customer),                 'can' => 'vm_viewAny'],
            ['label' => 'NAS',            'icon' => 'svg.db',       'count' => $customer->nas_count,                 'route' => route('nas.index', $customer),                'can' => 'nas_viewAny'],
            ['label' => 'Computer',       'icon' => 'svg.computer', 'count' => $customer->computers_count,           'route' => route('computer.index', $customer),           'can' => 'computer_viewAny'],
            ['label' => 'Drucker',        'icon' => 'svg.printer',  'count' => $customer->printers_count,            'route' => route('printer.index', $customer),            'can' => 'printer_viewAny'],
            ['label' => 'Kameras',        'icon' => 'svg.cam',      'count' => $customer->cameras_count,             'route' => route('camera.index', $customer),             'can' => 'camera_viewAny'],
            ['label' => 'Telefone',       'icon' => 'svg.phone',    'count' => $customer->phones_count,              'route' => route('phone.index', $customer),              'can' => 'phone_viewAny'],
            ['label' => 'AD-User',        'icon' => 'svg.user',     'count' => $customer->adusers_count,             'route' => route('aduser.index', $customer),             'can' => 'aduser_viewAny'],
        ];

        // Software-Lizenzen, die in den nächsten 60 Tagen ablaufen oder bereits abgelaufen sind
        $expiringLicenses = LicenseSoftware::where('customer_id', $customer->id)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(60))
            ->orderBy('end_date')
            ->get();

        // SSL/TLS-Zertifikate, die in den nächsten 60 Tagen ablaufen oder bereits abgelaufen sind
        $expiringCertificates = Certificate::where('customer_id', $customer->id)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(60))
            ->orderBy('expiry_date')
            ->get();

        // Einstieg zum Dokumentations-Assistenten: anbieten, wenn ein Durchlauf dieses Nutzers
        // offen ist ("Fortsetzen") oder der Kunde insgesamt noch kaum Inventar hat.
        $openWizardRun = DocumentationRun::where('customer_id', $customer->id)
            ->where('user_id', auth()->id())
            ->whereNull('completed_at')
            ->exists();

        $inventoryCount = $customer->servers_count + $customer->computers_count + $customer->vms_count
            + $customer->nas_count + $customer->networks_count + $customer->wifis_count
            + $customer->printers_count + $customer->cameras_count + $customer->phones_count
            + $customer->adusers_count;

        return view('customer.dashboard', compact(
            'customer', 'sites', 'contactpersons', 'tiles', 'expiringLicenses', 'expiringCertificates',
            'openWizardRun', 'inventoryCount'
        ));
    }

    /**
     * Die Dokumentation als PDF - in Auftrag gegeben, nicht sofort gerendert.
     *
     * Gemessen an zwei Kunden: 26 Server, 46 VMs, 53 Computer brauchen 136 MB
     * und 2 Sekunden, 40 Server, 90 VMs, 160 Computer schon 370 MB und 15
     * Sekunden. Im Request war das erst eine Fehlerseite und dann ein Rennen
     * gegen das Zeitlimit. Jetzt legt der Klick einen Auftrag an, den der
     * Scheduler abarbeitet; die Seite fragt den Stand ab.
     */
    public function viewPDF(Customer $customer)
    {
        $this->authorize('create_pdf');

        $laufend = PdfExport::where('customer_id', $customer->id)
            ->where('user_id', auth()->id())
            ->whereIn('status', [PdfExport::OFFEN, PdfExport::LAEUFT])
            ->exists();

        // Kein zweiter Auftrag, solange einer laeuft: Wer zweimal klickt, soll
        // nicht zweimal 370 MB anfordern.
        if (! $laufend) {
            $export = PdfExport::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'status' => PdfExport::OFFEN,
            ]);

            KundenPdfErzeugen::dispatch($export->id);
        }

        return back()->with('success', __('PDF wird erstellt — der Stand steht auf dieser Seite.'));
    }

    /**
     * Das fertige PDF ausliefern.
     *
     * Nur an den Besteller: Die Datei enthaelt alle Zugangsdaten des Kunden,
     * eine ID in der Adresse darf also nicht genuegen.
     */
    public function downloadPDF(Customer $customer, PdfExport $pdfExport)
    {
        $this->authorize('create_pdf');

        abort_if($pdfExport->customer_id !== $customer->id, 404);
        abort_if($pdfExport->user_id !== auth()->id(), 403);
        abort_unless($pdfExport->istFertig() && $pdfExport->path, 404);
        abort_unless(Storage::disk('local')->exists($pdfExport->path), 410);

        return Storage::disk('local')->download(
            $pdfExport->path,
            Str::slug($customer->name).'-dokumentation.pdf'
        );
    }

    // ADMIN Bereich
    public function index()
    {
        $customers = Customer::paginate(20);
        $customersCount = Customer::all()->count();

        return view('admin.customer.index', compact('customers', 'customersCount'));
    }

    public function create()
    {
        return view('admin.customer.create');
    }

    public function store(CustomerRequest $request)
    {
        Customer::create($request->validated());

        return redirect(route('admin.customer.index'));
    }

    public function edit($customer)
    {
        $customer = Customer::where('id', $customer)->firstOrFail();

        return view('admin.customer.edit', compact('customer'));
    }

    public function update(Customer $customer, CustomerRequest $request)
    {
        $customer->update($request->validated());

        return redirect(route('admin.customer.index', $customer));
    }
}
