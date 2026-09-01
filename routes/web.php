<?php

use App\Http\Controllers\AccesspointController;
use App\Http\Controllers\ADDomainController;
use App\Http\Controllers\ADGroupController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ADUserController;
use App\Http\Controllers\AgentTokenController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\ComputerController;
use App\Http\Controllers\ContactPersonController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DECTController;
use App\Http\Controllers\DeviceModelController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DynDNSController;
use App\Http\Controllers\EolController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\FTPServerController;
use App\Http\Controllers\InternetConnectionController;
use App\Http\Controllers\IoTDeviceController;
use App\Http\Controllers\IpPlanController;
use App\Http\Controllers\LicenseAccessController;
use App\Http\Controllers\LicenseSoftwareController;
use App\Http\Controllers\LicenseWindowsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginGeneralController;
use App\Http\Controllers\LoginWebsiteController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\MailboxProviderController;
use App\Http\Controllers\NASController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\NetworkSwitchController;
use App\Http\Controllers\OperatingSystemController;
use App\Http\Controllers\OtherClientController;
use App\Http\Controllers\PatchPanelController;
use App\Http\Controllers\PhoneController;
use App\Http\Controllers\PhoneSystemController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RackCatalogItemController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RecorderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\SecurepointUMAController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SshKeyController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UpsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VMController;
use App\Http\Controllers\WifiController;
use App\Http\Controllers\WizardController;
use App\Livewire\AdminAllgemein;
use App\Livewire\AdminApiToken;
use App\Livewire\AdminOperatingSystem;
use App\Livewire\AdminPapierkorb;
use App\Livewire\AdminProtokoll;
use App\Livewire\AdminProtokollHistorie;
use App\Livewire\GlobalSearch;
use App\Livewire\RemoteSearch;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::redirect('/', '/login');

Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');

// Die frueheren deutschen Adressen. Sie bleiben als Weiterleitung stehen -
// ein Lesezeichen auf /admin/papierkorb soll nicht ins Leere laufen. Neue
// Adressen sind durchgaengig englisch.
Route::permanentRedirect('/admin/papierkorb', '/admin/trash');
Route::permanentRedirect('/admin/protokoll-historie', '/admin/log-retention');
Route::permanentRedirect('/{customer}/assistent', '/{customer}/wizard');

// Das eigene Logo - bewusst ohne auth: Es steht auch auf der Anmeldeseite.
// Ein Logo ist nicht vertraulich, die Datei liegt aber trotzdem privat und
// geht durch den Controller, wie alle Dateien dieser App.
Route::get('/logo/{placement}', [BrandingController::class, 'logo'])->name('branding.logo');

// Das Bild eines Katalogelements. Hinter auth, aber ausserhalb des
// Adminbereichs: Gepflegt wird es dort, zu sehen ist es in jeder Rack-Ansicht -
// auch bei einem Kunden, der den Adminbereich nie betreten darf.
Route::middleware('auth')
    ->get('/rack-catalog-image/{rackcatalogitem}', [RackCatalogItemController::class, 'image'])
    ->name('rackcatalogitem.image');

// Das Bild eines Geraetemodells. Dieselbe Ueberlegung, und bewusst ohne
// Mandantenpruefung: Ein Modellfoto gehoert keinem Kunden - es zeigt die
// Frontblende einer "APC Smart-UPS 1500" und soll genau deshalb bei jedem
// Kunden erscheinen, bei dem eine steht.
Route::middleware('auth')
    ->get('/device-model-image/{devicemodel}', [DeviceModelController::class, 'image'])
    ->name('devicemodel.image');

// Techniker
// Bewusst ohne auth: Auch auf der Anmeldeseite soll man die Sprache wechseln
// koennen, und auf der Demo ist der geteilte Zugang gesperrt.
Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'isTechniker'])->group(function () {

    // GlobalSearch
    Route::get('/remotesearch', RemoteSearch::class)->name('search.remote');
});

// Admin Bereich
Route::middleware(['auth', 'isAdmin'])->group(function () {
    Route::prefix('admin')->group(function () {

        // Papierkorb ueber alle Kunden - sehen, was sich angesammelt hat, und
        // es endgueltig loswerden.
        Route::get('/trash', AdminPapierkorb::class)
            ->middleware('can:admin_trash')->name('admin.trash');

        // Wie lange das Protokoll - und damit die bisherigen Kennwoerter -
        // aufbewahrt wird.
        Route::get('/log-retention', AdminProtokollHistorie::class)
            ->middleware('can:admin_activity')->name('admin.logretention');

        // Einstellungen der Installation
        Route::middleware('can:admin_setting')->group(function () {
            // Eigener Name und eigene Logos - Livewire, damit jede Aenderung
            // sofort gilt statt erst nach einem Speichern-Knopf.
            Route::get('/general', AdminAllgemein::class)->name('admin.general.index');

            Route::get('/setting', [SettingController::class, 'index'])->name('admin.setting.index');
            Route::patch('/setting', [SettingController::class, 'update'])->name('admin.setting.update');
        });

        // Aktivitätsprotokoll - mit Suche und Filtern, deshalb Livewire.
        Route::get('/activity', AdminProtokoll::class)
            ->middleware('can:admin_activity')->name('admin.activity.index');

        // Token anlegen und widerrufen. Vorher gab diese Adresse rohes JSON
        // zurueck und legte bei jedem Aufruf einen weiteren Token an - ein
        // Menuepunkt darauf haette beim Klicken Token erzeugt.
        Route::get('/apitoken', AdminApiToken::class)
            ->middleware('can:admin_apitoken')->name('admin.apitoken');

        Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');

        // Kunden
        Route::middleware('can:admin_customer')->group(function () {
            Route::get('/customer', [CustomerController::class, 'index'])->name('admin.customer.index');
            Route::post('/customer', [CustomerController::class, 'store'])->name('admin.customer.store');
            Route::get('/customer/create', [CustomerController::class, 'create'])->name('admin.customer.create');
            Route::get('/customer/{customer}/edit', [CustomerController::class, 'edit'])->name('admin.customer.edit');
            Route::patch('/customer/{customer}', [CustomerController::class, 'update'])->name('admin.customer.update');
        });

        // Users
        Route::middleware('can:admin_user')->group(function () {
            Route::get('/user', [UserController::class, 'index'])->name('admin.user.index');
            Route::post('/user', [UserController::class, 'store'])->name('admin.user.store');
            Route::get('/user/create', [UserController::class, 'create'])->name('admin.user.create');
            Route::get('/user/{user}/edit', [UserController::class, 'edit'])->name('admin.user.edit');
            Route::patch('/user/{user}', [UserController::class, 'update'])->name('admin.user.update');
            Route::delete('/user/{user}', [UserController::class, 'destroy'])->name('admin.user.destroy');

            // Verlorenes Telefon: Ohne diesen Weg waere ein Nutzer mit
            // eingeschalteter zweiter Stufe und ohne Wiederherstellungscode
            // dauerhaft ausgesperrt.
            Route::delete('/user/{user}/zweite-stufe', [UserController::class, 'zweiteStufeZuruecksetzen'])
                ->name('admin.user.two-factor');

            // Einladung erneut schicken: im Spam gelandet, Link abgelaufen,
            // versehentlich geloescht - das ist der Alltag, nicht der Fehler.
            Route::post('/user/{user}/einladung', [UserController::class, 'einladen'])
                ->name('admin.user.einladung');
        });

        // Rolen
        Route::middleware('can:admin_role')->group(function () {
            Route::get('/role', [RoleController::class, 'index'])->name('admin.role.index');
            Route::post('/role', [RoleController::class, 'store'])->name('admin.role.store');
            Route::get('/role/create', [RoleController::class, 'create'])->name('admin.role.create');
            Route::get('/role/{role}/edit', [RoleController::class, 'edit'])->name('admin.role.edit');
            Route::patch('/role/{role}', [RoleController::class, 'update'])->name('admin.role.update');
            Route::delete('/role/{role}', [RoleController::class, 'destroy'])->name('admin.role.destroy');
        });

        // Operating Systems. Die Liste steht im Menue unter "Auswahlmenues",
        // neben Diensten und Mail-Anbietern - deshalb dasselbe Recht. Das
        // eigene Recht traegt die EOL-Auswertung, die einen eigenen Menuepunkt
        // hat.
        Route::middleware('can:admin_catalog')->group(function () {
            // Livewire statt Controller: einzige Aenderung ist die Suche,
            // Anlegen/Bearbeiten bleiben eigene Seiten.
            Route::get('/operatingsystem', AdminOperatingSystem::class)->name('admin.operatingsystem.index');
            Route::post('/operatingsystem/create', [OperatingSystemController::class, 'store'])->name('admin.operatingsystem.store');
            Route::get('/operatingsystem/create', [OperatingSystemController::class, 'create'])->name('admin.operatingsystem.create');
            Route::get('/operatingsystem/{operatingSystem}/edit', [OperatingSystemController::class, 'edit'])->name('admin.operatingsystem.edit');
            Route::patch('/operatingsystem/{operatingSystem}', [OperatingSystemController::class, 'update'])->name('admin.operatingsystem.update');
        });

        // Betroffene Geraete nach Kunde: Welcher Kunde hat wie viele Maschinen
        // auf einem System, dessen Support endet? Gehoert zu den
        // Betriebssystemen - dort steht das Support-Ende.
        Route::get('/eol', [EolController::class, 'index'])
            ->middleware('can:admin_operatingsystem')->name('admin.eol.index');

        // Die drei Kataloge teilen sich ein Recht: Wer Dienste pflegen darf,
        // pflegt auch Mailbox-Anbieter und Rack-Einbauten - es ist dieselbe
        // Art von Arbeit an denselben Auswahllisten.
        Route::middleware('can:admin_catalog')->group(function () {
            // Dienste-Katalog (Name und Farbe der Kacheln in den Geraetelisten)
            Route::get('/service', [ServiceController::class, 'index'])->name('admin.service.index');
            Route::post('/service/create', [ServiceController::class, 'store'])->name('admin.service.store');
            Route::get('/service/create', [ServiceController::class, 'create'])->name('admin.service.create');
            Route::get('/service/{service}/edit', [ServiceController::class, 'edit'])->name('admin.service.edit');
            Route::patch('/service/{service}', [ServiceController::class, 'update'])->name('admin.service.update');
            Route::delete('/service/{service}', [ServiceController::class, 'destroy'])->name('admin.service.destroy');

            // Mailbox Providor
            Route::get('/mailboxprovider', [MailboxProviderController::class, 'index'])->name('admin.mailboxprovider.index');
            Route::post('/mailboxprovider/create', [MailboxProviderController::class, 'store'])->name('admin.mailboxprovider.store');
            Route::get('/mailboxprovider/create', [MailboxProviderController::class, 'create'])->name('admin.mailboxprovider.create');
            Route::get('/mailboxprovider/{mailboxprovider}/edit', [MailboxProviderController::class, 'edit'])->name('admin.mailboxprovider.edit');
            Route::patch('/mailboxprovider/{mailboxprovider}', [MailboxProviderController::class, 'update'])->name('admin.mailboxprovider.update');

            // Rack-Katalog (passive Einbauten wie Patchfelder und Blindplatten)
            Route::get('/rackcatalogitem', [RackCatalogItemController::class, 'index'])->name('admin.rackcatalogitem.index');
            Route::post('/rackcatalogitem/create', [RackCatalogItemController::class, 'store'])->name('admin.rackcatalogitem.store');
            Route::get('/rackcatalogitem/create', [RackCatalogItemController::class, 'create'])->name('admin.rackcatalogitem.create');
            Route::get('/rackcatalogitem/{rackcatalogitem}/edit', [RackCatalogItemController::class, 'edit'])->name('admin.rackcatalogitem.edit');
            Route::patch('/rackcatalogitem/{rackcatalogitem}', [RackCatalogItemController::class, 'update'])->name('admin.rackcatalogitem.update');
            Route::delete('/rackcatalogitem/{rackcatalogitem}', [RackCatalogItemController::class, 'destroy'])->name('admin.rackcatalogitem.destroy');

            Route::get('/devicemodel', [DeviceModelController::class, 'index'])->name('admin.devicemodel.index');
            Route::post('/devicemodel/create', [DeviceModelController::class, 'store'])->name('admin.devicemodel.store');
            Route::get('/devicemodel/create', [DeviceModelController::class, 'create'])->name('admin.devicemodel.create');
            Route::get('/devicemodel/{devicemodel}/edit', [DeviceModelController::class, 'edit'])->name('admin.devicemodel.edit');
            Route::patch('/devicemodel/{devicemodel}', [DeviceModelController::class, 'update'])->name('admin.devicemodel.update');
            Route::delete('/devicemodel/{devicemodel}', [DeviceModelController::class, 'destroy'])->name('admin.devicemodel.destroy');
        });

    });
});

// Profile
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

// Customer
Route::middleware('auth')->get('/search', GlobalSearch::class)->name('search.global');
Route::get('/customer/search', [CustomerController::class, 'search'])->name('customer.search');
Route::get('/{customer}', [CustomerController::class, 'dashboard'])->name('customer.dashboard');
Route::post('/{customer}/view-pdf', [CustomerController::class, 'viewPDF'])->name('customer.view-pdf');
// Das fertige PDF abholen. Nur der Besteller darf es laden - es enthaelt alle
// Zugangsdaten des Kunden.
Route::get('/{customer}/pdf/{pdfExport}', [CustomerController::class, 'downloadPDF'])->name('customer.pdf-download');

Route::middleware(['auth', 'isCustomer'])->group(function () {
    Route::prefix('{customer}')->group(function () {
        Route::scopeBindings()->group(function () {

            // Site
            Route::post('filter', [SiteController::class, 'filter'])->name('filter.site');

            // IP-Plan je VLAN
            Route::get('ip-plan', [IpPlanController::class, 'index'])->name('ipplan.index');

            // Papierkorb
            Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
            Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');

            Route::get('agent', [AgentTokenController::class, 'index'])->name('agent.index');
            Route::post('agent', [AgentTokenController::class, 'store'])->name('agent.store');
            Route::delete('agent/{agentToken}', [AgentTokenController::class, 'destroy'])->name('agent.destroy');

            // Dokumentations-Assistent (geführte Erstaufnahme)
            Route::get('wizard', [WizardController::class, 'index'])->name('wizard.index');

            Route::resource('site', SiteController::class)->only(['index']);
            Route::resource('contactperson', ContactPersonController::class)->only(['index']);

            Route::resource('router', RouterController::class)->only(['index']);
            Route::resource('firewall', FirewallController::class)->only(['index']);
            Route::resource('securepointuma', SecurepointUMAController::class)->only(['index']);
            Route::resource('network', NetworkController::class)->only(['index']);
            Route::resource('accesspoint', AccesspointController::class)->only(['index']);
            Route::resource('server', ServerController::class)->only(['index']);
            Route::resource('cluster', ClusterController::class)->only(['index']);
            Route::resource('vm', VMController::class)->only(['index']);
            Route::resource('networkswitch', NetworkSwitchController::class, ['parameters' => ['networkswitch' => 'networkswitch']])->only(['index']);
            Route::resource('rack', RackController::class)->except(['show']);
            Route::resource('patchpanel', PatchPanelController::class, ['parameters' => ['patchpanel' => 'patchpanel']])->except(['show']);
            Route::resource('nas', NASController::class, ['parameters' => ['nas' => 'nas']])->only(['index']);
            Route::resource('addomain', ADDomainController::class)->only(['index']);
            Route::resource('aduser', ADUserController::class)->only(['index']);
            Route::resource('adgroup', ADGroupController::class)->only(['index']);
            Route::resource('loginwebsite', LoginWebsiteController::class)->only(['index']);
            Route::resource('logingeneral', LoginGeneralController::class)->only(['index']);
            // Nur die Liste: Anlegen, Bearbeiten und Loeschen laufen ueber das Modal.
            Route::get('sshkey', [SshKeyController::class, 'index'])->name('sshkey.index');
            Route::resource('phonesystem', PhoneSystemController::class)->only(['index']);
            Route::resource('phone', PhoneController::class)->only(['index']);
            Route::resource('dect', DECTController::class)->only(['index']);
            Route::resource('mailbox', MailboxController::class)->only(['index']);
            Route::resource('wifi', WifiController::class)->only(['index']);
            Route::resource('computer', ComputerController::class)->only(['index']);
            Route::resource('iotdevice', IoTDeviceController::class)->only(['index']);
            Route::resource('machine', MachineController::class)->only(['index']);
            Route::resource('otherclient', OtherClientController::class)->only(['index']);
            Route::resource('printer', PrinterController::class)->only(['index']);
            Route::resource('ftpserver', FTPServerController::class)->only(['index']);
            Route::resource('recorder', RecorderController::class)->only(['index']);
            Route::resource('camera', CameraController::class)->only(['index']);
            Route::resource('ups', UpsController::class, ['parameters' => ['ups' => 'ups']])->only(['index']);
            Route::resource('internetconnection', InternetConnectionController::class)->only(['index']);
            Route::resource('domain', DomainController::class)->only(['index']);
            Route::resource('certificate', CertificateController::class)->only(['index']);
            Route::resource('backup', BackupController::class)->only(['index']);
            Route::resource('dyndns', DynDNSController::class, ['parameters' => ['dyndns' => 'dyndns']])->only(['index']);

            Route::get('/licensesoftware/{licensesoftware}/download', [LicenseSoftwareController::class, 'download'])->name('licensesoftware.download');
            Route::resource('licensesoftware', LicenseSoftwareController::class, ['parameters' => ['licensesoftware' => 'licensesoftware']])->only(['index']);
            Route::get('/licensewindows/{licensewindows}/download', [LicenseWindowsController::class, 'download'])->name('licensewindows.download');
            Route::resource('licensewindows', LicenseWindowsController::class, ['parameters' => ['licensewindows' => 'licensewindows']])->only(['index']);
            Route::get('/licenseaccess/{licenseaccess}/download', [LicenseAccessController::class, 'download'])->name('licenseaccess.download');
            Route::resource('licenseaccess', LicenseAccessController::class, ['parameters' => ['licenseaccess' => 'licenseaccess']])->only(['index']);

            // File
            // Die Liste als Livewire - Suche, Filter und Sortierung. Hochladen
            // und Herunterladen bleiben beim Controller.
            Route::get('file', [FileController::class, 'index'])->name('file.index');
            Route::post('file', [FileController::class, 'store'])->name('file.store');
            Route::delete('file/{file}', [FileController::class, 'destroy'])->name('file.destroy');
            Route::get('/file/{file}', [FileController::class, 'download']);

        });
    });
});
