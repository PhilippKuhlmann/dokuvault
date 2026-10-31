<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Jobs\KundenPdfErzeugen;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\PdfExport;
use App\Models\Setting;
use App\Support\Kundenuebersicht;
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
        // Auch Admins dürfen hier suchen und in die Dokumentation eines Kunden
        // springen - früher landeten sie stattdessen hart auf /admin.
        session()->put('site', 'all');

        $customers = null;

        if (request('search')) {
            $customers = Customer::whereEnthaelt('name', request('search'))->get();
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
        // Fristen, Standorte, Ansprechpartner, Agenten und die letzten
        // Aenderungen rechnet Kundenuebersicht - dieselbe Klasse bedient die
        // iOS-App, damit beide dieselben Tage zeigen.
        $uebersicht = new Kundenuebersicht($customer, auth()->user());

        $sites = $uebersicht->standorte();
        $contactpersons = $uebersicht->ansprechpartner();

        // Inventar-Zähler (in einer Abfrage via loadCount)
        $customer->loadCount([
            'internetconnections', 'firewalls', 'networkswitches', 'accesspoints',
            'servers', 'vms', 'nas', 'backups', 'computers', 'printers',
            'phones', 'adusers',
            // Not tiles, but part of $inventoryCount below.
            'networks', 'wifis', 'cameras',
        ]);

        $tiles = [
            // Only the twelve one looks up most - two full rows of six. With
            // every type (25 at one point) the row was a wall of numbers before
            // anything of substance; the rest is one click away in the sidebar.
            // Outside in, as one searches on site: the line and what hangs off
            // it, then the servers, then the workstations.
            ['label' => 'Internet / WAN', 'icon' => 'svg.link',     'count' => $customer->internetconnections_count, 'route' => route('internetconnection.index', $customer), 'can' => 'internetconnection_viewAny'],
            ['label' => 'Firewalls',      'icon' => 'svg.fire',     'count' => $customer->firewalls_count,           'route' => route('firewall.index', $customer),           'can' => 'firewall_viewAny'],
            ['label' => 'Switches',       'icon' => 'svg.group',    'count' => $customer->networkswitches_count,     'route' => route('networkswitch.index', $customer),      'can' => 'networkswitch_viewAny'],
            ['label' => 'Accesspoints',   'icon' => 'svg.signal',   'count' => $customer->accesspoints_count,        'route' => route('accesspoint.index', $customer),        'can' => 'accesspoint_viewAny'],
            ['label' => 'Server',         'icon' => 'svg.servers',  'count' => $customer->servers_count,             'route' => route('server.index', $customer),             'can' => 'server_viewAny'],
            ['label' => 'VMs',            'icon' => 'svg.server',   'count' => $customer->vms_count,                 'route' => route('vm.index', $customer),                 'can' => 'vm_viewAny'],
            ['label' => 'NAS',            'icon' => 'svg.db',       'count' => $customer->nas_count,                 'route' => route('nas.index', $customer),                'can' => 'nas_viewAny'],
            ['label' => 'Backups',        'icon' => 'svg.db',       'count' => $customer->backups_count,             'route' => route('backup.index', $customer),             'can' => 'backup_viewAny'],
            ['label' => 'Computer',       'icon' => 'svg.computer', 'count' => $customer->computers_count,           'route' => route('computer.index', $customer),           'can' => 'computer_viewAny'],
            ['label' => 'Drucker',        'icon' => 'svg.printer',  'count' => $customer->printers_count,            'route' => route('printer.index', $customer),            'can' => 'printer_viewAny'],
            ['label' => 'Telefone',       'icon' => 'svg.phone',    'count' => $customer->phones_count,              'route' => route('phone.index', $customer),              'can' => 'phone_viewAny'],
            ['label' => 'AD-User',        'icon' => 'svg.user',     'count' => $customer->adusers_count,             'route' => route('aduser.index', $customer),             'can' => 'aduser_viewAny'],
        ];

        $expiringLicenses = $uebersicht->lizenzen();
        $expiringCertificates = $uebersicht->zertifikate();
        $expiringWarranties = $uebersicht->garantien();
        $endOfSupport = $uebersicht->supportEnde();
        $recentChanges = $uebersicht->letzteAenderungen();
        $agentWarnings = $uebersicht->agentenWarnungen();

        // Einstieg zum Dokumentations-Assistenten: anbieten, wenn ein Durchlauf dieses Nutzers
        // offen ist ("Fortsetzen") oder der Kunde insgesamt noch kaum Inventar hat.
        // Wurde die Erstaufnahme für diesen Kunden schon einmal abgeschlossen (auch
        // per "Als erledigt markieren"), wird "Starten" nicht mehr vorgeschlagen.
        $openWizardRun = DocumentationRun::where('customer_id', $customer->id)
            ->where('user_id', auth()->id())
            ->whereNull('completed_at')
            ->exists();

        $wizardCompleted = DocumentationRun::where('customer_id', $customer->id)
            ->whereNotNull('completed_at')
            ->exists();

        $inventoryCount = $customer->servers_count + $customer->computers_count + $customer->vms_count
            + $customer->nas_count + $customer->networks_count + $customer->wifis_count
            + $customer->printers_count + $customer->cameras_count + $customer->phones_count
            + $customer->adusers_count;

        return view('customer.dashboard', compact(
            'customer', 'sites', 'contactpersons', 'tiles', 'expiringLicenses', 'expiringCertificates',
            'expiringWarranties', 'endOfSupport', 'recentChanges', 'agentWarnings',
            'openWizardRun', 'wizardCompleted', 'inventoryCount'
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

        // The PDF carries every password of the customer - its download
        // belongs in the log like a single password looked at.
        activity()
            ->event('pdf_heruntergeladen')
            ->performedOn($customer)
            ->causedBy(auth()->user())
            ->withProperties(['objekt' => $customer->name, 'attributes' => ['IP' => request()->ip()]])
            ->log('PDF mit Zugangsdaten heruntergeladen');

        return Storage::disk('local')->download(
            $pdfExport->path,
            Str::slug($customer->name).'-dokumentation.pdf'
        );
    }

    // ADMIN Bereich
    public function index()
    {
        $customers = Customer::paginate(Setting::seiteAdmin());
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
