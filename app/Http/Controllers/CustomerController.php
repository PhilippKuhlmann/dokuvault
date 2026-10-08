<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Jobs\KundenPdfErzeugen;
use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Backup;
use App\Models\Certificate;
use App\Models\Computer;
use App\Models\Concerns\HatBeschaffung;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\LicenseSoftware;
use App\Models\PdfExport;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Models\VM;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

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
        $sites = Site::where('customer_id', $customer->id)->get();
        $contactpersons = ContactPerson::where('customer_id', $customer->id)->get();

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

        // Software-Lizenzen, die bald ablaufen oder bereits abgelaufen sind
        $expiringLicenses = LicenseSoftware::where('customer_id', $customer->id)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(Setting::fristVertraege()))
            ->orderBy('end_date')
            ->get();

        // SSL/TLS-Zertifikate, die bald ablaufen oder bereits abgelaufen sind
        $expiringCertificates = Certificate::where('customer_id', $customer->id)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(Setting::fristVertraege()))
            ->orderBy('expiry_date')
            ->get();

        // Hardware, deren Garantie ausläuft oder schon ausgelaufen ist. Eigene
        // Frist: Eine Lizenz verlängert man, ein Gerät muss ersetzt werden.
        $expiringWarranties = $this->ablaufendeGarantien($customer);

        // Support ending: devices (eol_date) and operating systems together -
        // "no more security updates" is the same question for both.
        $endOfSupport = $this->endOfSupport($customer);

        $recentChanges = $this->recentChanges($customer);

        // Only for those who manage agents, and only where there are any.
        $agentWarnings = Gate::allows('agent_manage') ? $this->agentWarnings($customer) : null;

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
     * What needs attention with the agents: machines that stopped reporting,
     * runs that failed, backups that failed or warned, tokens about to
     * expire. Null when the customer has
     * neither agents nor tokens - then there is no tile at all.
     *
     * @return Collection<int, array{name: string, art: string, text: string, schwer: bool}>|null
     */
    private function agentWarnings(Customer $customer): ?Collection
    {
        $installations = AgentInstallation::where('customer_id', $customer->id)->orderBy('hostname')->get();
        $tokens = AgentToken::where('customer_id', $customer->id)->get();
        // Backups whose last run failed or warned, as an agent reported it.
        $backups = Backup::where('customer_id', $customer->id)
            ->whereIn('last_status', ['failed', 'warning'])
            ->orderBy('name')
            ->get();

        if ($installations->isEmpty() && $tokens->isEmpty()) {
            return null;
        }

        $warnings = collect();

        foreach ($installations as $installation) {
            if ($installation->isStale()) {
                $warnings->push([
                    'name' => $installation->hostname,
                    'art' => __('Agent'),
                    'text' => $installation->last_seen_at
                        ? __('meldet nicht seit :zeit', ['zeit' => $installation->last_seen_at->diffForHumans(null, true)])
                        : __('meldet nicht'),
                    'schwer' => true,
                ]);
            }

            foreach ($installation->failedRoles() as $role) {
                $warnings->push([
                    'name' => $installation->hostname,
                    'art' => __(AgentInstallation::availableRoles($installation->kind)[$role] ?? $role),
                    'text' => __('fehlgeschlagen'),
                    'schwer' => true,
                ]);
            }
        }

        foreach ($backups as $backup) {
            $warnings->push([
                'name' => $backup->name,
                'art' => __('Backup').($backup->software ? ' · '.$backup->software : ''),
                'text' => __(Backup::STATUS[$backup->last_status]),
                'schwer' => $backup->last_status === 'failed',
            ]);
        }

        foreach ($tokens as $token) {
            if ($token->expires_at === null || $token->expires_at->gt(now()->addDays(30))) {
                continue;
            }
            $warnings->push([
                'name' => $token->name ?: 'Token #'.$token->id,
                'art' => __('Agent-Token'),
                'text' => $token->istAbgelaufen()
                    ? __('abgelaufen')
                    : __('läuft ab in :tage Tagen', ['tage' => (int) now()->startOfDay()->diffInDays($token->expires_at->copy()->startOfDay())]),
                'schwer' => $token->istAbgelaufen(),
            ]);
        }

        return $warnings;
    }

    /**
     * Devices and operating systems whose support has ended or ends soon.
     *
     * Two sources: the support end of the hardware (eol_date via
     * HatBeschaffung, found the same way as the warranties) and that of the
     * operating system on servers, VMs and computers. Uses the EOL deadline
     * from the settings - replacing a server needs more lead time than
     * renewing a license.
     */
    private function endOfSupport(Customer $customer): Collection
    {
        $grenze = now()->addDays(Setting::fristEol())->toDateString();
        $eintrag = fn ($name, $art, $datum, $slug, $id) => [
            'name' => $name ?: '—',
            'art' => $art,
            'datum' => $datum,
            'tage' => (int) now()->startOfDay()->diffInDays($datum->copy()->startOfDay(), false),
            // ?highlight= like the global search: the list jumps to the entry
            // and marks it.
            'url' => route($slug.'.index', [$customer, 'highlight' => $id]),
        ];

        $geraete = collect(config('custom.trashables'))
            ->filter(fn ($e, $slug) => in_array(HatBeschaffung::class, class_uses_recursive($e[0]), true)
                && Gate::allows($slug.'_viewAny'))
            ->flatMap(fn ($e, $slug) => $e[0]::where('customer_id', $customer->id)
                ->whereNotNull('eol_date')
                ->whereDate('eol_date', '<=', $grenze)
                ->orderBy('eol_date')
                ->limit(10)
                ->get()
                ->map(fn ($g) => $eintrag($g->name ?? $g->serialNumber, __($e[1]), $g->eol_date, $slug, $g->id)));

        $systeme = collect(['server' => Server::class, 'vm' => VM::class, 'computer' => Computer::class])
            ->filter(fn ($klasse, $slug) => Gate::allows($slug.'_viewAny'))
            ->flatMap(fn ($klasse, $slug) => $klasse::where('customer_id', $customer->id)
                ->whereHas('operatingSystem', fn ($os) => $os->whereNotNull('eol_date')->whereDate('eol_date', '<=', $grenze))
                ->with('operatingSystem')
                ->limit(10)
                ->get()
                ->map(fn ($m) => $eintrag(
                    $m->name,
                    __(config('custom.trashables')[$slug][1]).' · '.$m->operatingSystem->name,
                    $m->operatingSystem->eol_date,
                    $slug,
                    $m->id
                )));

        return $geraete->concat($systeme)->sortBy('datum')->values();
    }

    /**
     * The latest changes to this customer's documentation.
     *
     * Only types the user may list, so the tile shows nothing whose list
     * stays closed to them. A deletion is a change too.
     */
    private function recentChanges(Customer $customer, int $anzahl = 8): Collection
    {
        // Every trashable type carries customer_id (the trash itself relies on it).
        $typen = collect(config('custom.trashables'))
            ->filter(fn ($e, $slug) => Gate::allows($slug.'_viewAny'));

        if ($typen->isEmpty()) {
            return collect();
        }

        $slugs = $typen->mapWithKeys(fn ($e, $slug) => [$e[0] => [$slug, $e[1]]]);

        // customer_id on the entry itself, not "whose object is it?": one
        // OR EXISTS per object type took 250 ms with 96,000 entries.
        return Activity::with('causer')
            ->where('customer_id', $customer->id)
            ->whereIn('subject_type', $typen->pluck(0)->all())
            ->latest()
            ->limit($anzahl)
            ->get()
            ->map(fn ($a) => [
                'name' => $a->properties['objekt'] ?? '#'.$a->subject_id,
                'art' => __($slugs[$a->subject_type][1] ?? class_basename($a->subject_type)),
                'ereignis' => $a->event,
                'wer' => $a->causer?->name,
                'wann' => $a->created_at,
                // Highlighted like from the global search - unless deleted:
                // the entry is in the trash, there is nothing to mark.
                'url' => isset($slugs[$a->subject_type]) && Route::has($slugs[$a->subject_type][0].'.index')
                    ? route($slugs[$a->subject_type][0].'.index', $a->event === 'deleted'
                        ? [$customer]
                        : [$customer, 'highlight' => $a->subject_id])
                    : null,
            ]);
    }

    /**
     * Hardware, deren Garantie ablaeuft - ueber alle Geraetearten hinweg.
     *
     * Die Geraeteliste kommt aus config('custom.trashables') und wird auf die
     * Models eingeschraenkt, die HatBeschaffung einbinden. Damit gibt es keine
     * zweite Liste, die man beim naechsten Geraetetyp vergessen kann: Wer den
     * Trait einbaut, ist hier automatisch dabei.
     *
     * Je Geraeteart wird die Sichtbarkeit geprueft. Ohne das stuenden auf dem
     * Dashboard Geraete, deren Liste der Nutzer nicht oeffnen darf.
     */
    private function ablaufendeGarantien(Customer $customer, ?int $tage = null): Collection
    {
        $tage ??= Setting::fristGarantie();

        return collect(config('custom.trashables'))
            ->filter(fn ($eintrag, $slug) => in_array(HatBeschaffung::class, class_uses_recursive($eintrag[0]), true)
                && Gate::allows($slug.'_viewAny'))
            ->flatMap(function ($eintrag, $slug) use ($customer, $tage) {
                [$klasse, $bezeichnung] = $eintrag;

                return $klasse::where('customer_id', $customer->id)
                    ->garantieLaeuftAb($tage)
                    ->orderBy('warranty_until')
                    // Mehr als eine Handvoll je Geraeteart passt nicht auf die
                    // Karte; die vollstaendige Liste steht hinter dem Link.
                    ->limit(10)
                    ->get()
                    ->map(fn ($geraet) => [
                        // Telefone und einige andere haben keine name-Spalte.
                        'name' => $geraet->name ?? $geraet->serialNumber ?? '—',
                        'art' => $bezeichnung,
                        'datum' => $geraet->warranty_until,
                        'tage' => $geraet->garantieTage(),
                        'url' => route($slug.'.index', [$customer, 'highlight' => $geraet->id]),
                    ]);
            })
            ->sortBy('datum')
            ->values();
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
