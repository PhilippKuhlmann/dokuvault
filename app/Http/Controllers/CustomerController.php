<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
// use function Spatie\LaravelPdf\Support\pdf;
use App\Http\Requests\CustomerRequest;
use App\Models\ContactPerson;
use App\Models\DocumentationRun;
use App\Models\LicenseSoftware;
use App\Models\Role;
use App\Models\Site;

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

        $customers = NULL;

        if (request('search')) {
            $customers = Customer::where('name', 'like', '%' . request('search') . '%')->get();
            if ($customers->isempty())
            {
                $customers = NULL;
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
            'servers', 'computers', 'vms', 'nas', 'networks',
            'wifis', 'printers', 'cameras', 'phones', 'adusers',
        ]);

        $tiles = [
            ['label' => 'Server',    'icon' => 'svg.servers',  'count' => $customer->servers_count,   'route' => route('server.index', $customer),   'can' => 'server_viewAny'],
            ['label' => 'Computer',  'icon' => 'svg.computer', 'count' => $customer->computers_count, 'route' => route('computer.index', $customer), 'can' => 'computer_viewAny'],
            ['label' => 'VMs',       'icon' => 'svg.server',   'count' => $customer->vms_count,       'route' => route('vm.index', $customer),       'can' => 'vm_viewAny'],
            ['label' => 'NAS',       'icon' => 'svg.db',       'count' => $customer->nas_count,       'route' => route('nas.index', $customer),      'can' => 'nas_viewAny'],
            ['label' => 'Netzwerke', 'icon' => 'svg.wifi',     'count' => $customer->networks_count,  'route' => route('network.index', $customer), 'can' => 'network_viewAny'],
            ['label' => 'WLAN',      'icon' => 'svg.signal',   'count' => $customer->wifis_count,     'route' => route('wifi.index', $customer),     'can' => 'wifi_viewAny'],
            ['label' => 'Drucker',   'icon' => 'svg.printer',  'count' => $customer->printers_count,  'route' => route('printer.index', $customer), 'can' => 'printer_viewAny'],
            ['label' => 'Kameras',   'icon' => 'svg.cam',      'count' => $customer->cameras_count,   'route' => route('camera.index', $customer),  'can' => 'camera_viewAny'],
            ['label' => 'Telefone',  'icon' => 'svg.phone',    'count' => $customer->phones_count,    'route' => route('phone.index', $customer),   'can' => 'phone_viewAny'],
            ['label' => 'AD-User',   'icon' => 'svg.user',     'count' => $customer->adusers_count,   'route' => route('aduser.index', $customer),  'can' => 'aduser_viewAny'],
        ];

        // Software-Lizenzen, die in den nächsten 60 Tagen ablaufen oder bereits abgelaufen sind
        $expiringLicenses = LicenseSoftware::where('customer_id', $customer->id)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(60))
            ->orderBy('end_date')
            ->get();

        // SSL/TLS-Zertifikate, die in den nächsten 60 Tagen ablaufen oder bereits abgelaufen sind
        $expiringCertificates = \App\Models\Certificate::where('customer_id', $customer->id)
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

    public function viewPDF(Customer $customer)
    {
        $pdf = Pdf::loadView('pdf.customer', [
            'customer' => $customer,
        ],);

        return $pdf->stream();


        // return pdf()
        //     ->view('pdf.customer', compact('customer'))
        //     ->footerView('pdf.footer')
        //     ->name('dokumentation.pdf');
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
