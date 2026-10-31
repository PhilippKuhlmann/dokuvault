<?php

use App\Http\Controllers\AccesspointController;
use App\Http\Controllers\ADDomainController;
use App\Http\Controllers\ADGroupController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ADUserController;
use App\Http\Controllers\AgentTokenController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ChecklistController;
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
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\NASController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\NetworkMapController;
use App\Http\Controllers\NetworkShareController;
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
use App\Http\Controllers\RemoteConnectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScanTargetController;
use App\Http\Controllers\SecurepointUMAController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SipAccountController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SshKeyController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UpsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VMController;
use App\Http\Controllers\WifiController;
use App\Http\Controllers\WizardController;
use App\Livewire\AdminActivityLog;
use App\Livewire\AdminAgentSettings;
use App\Livewire\AdminAgentStats;
use App\Livewire\AdminApiLoad;
use App\Livewire\AdminApiToken;
use App\Livewire\AdminBackups;
use App\Livewire\AdminBackupSettings;
use App\Livewire\AdminDataGrowth;
use App\Livewire\AdminGeneral;
use App\Livewire\AdminLogRetention;
use App\Livewire\AdminNotifications;
use App\Livewire\AdminOperatingSystem;
use App\Livewire\AdminRetention;
use App\Livewire\AdminSecurity;
use App\Livewire\AdminSystem;
use App\Livewire\AdminTrash;
use App\Livewire\AdminUsage;
use App\Livewire\GlobalSearch;
use App\Livewire\RemoteSearch;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::redirect('/', '/login');

Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');

// The former German addresses. They stay as redirects - a bookmark on
// /admin/papierkorb must not lead nowhere. New addresses are English
// throughout.
Route::permanentRedirect('/admin/papierkorb', '/admin/trash');
Route::permanentRedirect('/admin/protokoll-historie', '/admin/log-retention');
Route::permanentRedirect('/{customer}/assistent', '/{customer}/wizard');

// The own logo - deliberately without auth: it stands on the sign-in page
// too. A logo is not confidential, but the file lies privately all the same
// and goes through the controller, like every file in this app.
Route::get('/logo/{placement}', [BrandingController::class, 'logo'])->name('branding.logo');

// The image of a catalog item. Behind auth but outside the admin area: it is
// maintained there and visible in every rack view - including at a customer
// who may never enter the admin area.
Route::middleware('auth')
    ->get('/rack-catalog-image/{rackcatalogitem}', [RackCatalogItemController::class, 'image'])
    ->name('rackcatalogitem.image');

// The image of a device model. The same reasoning, and deliberately without
// a tenant check: a model photo belongs to no customer - it shows the front
// panel of an "APC Smart-UPS 1500" and should appear for exactly that reason
// at every customer who has one.
Route::middleware('auth')
    ->get('/device-model-image/{devicemodel}', [DeviceModelController::class, 'image'])
    ->name('devicemodel.image');

// Technicians
// Deliberately without auth: the language should be switchable on the
// sign-in page too, and on the demo the shared account is locked.
Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'IsTechnician'])->group(function () {

    // GlobalSearch
    Route::get('/remotesearch', RemoteSearch::class)->name('search.remote');
});

// Admin area
Route::middleware(['auth', 'isAdmin'])->group(function () {
    Route::prefix('admin')->group(function () {

        // Trash across all customers - see what has piled up and get rid of
        // it for good.
        Route::get('/trash', AdminTrash::class)
            ->middleware('can:admin_trash')->name('admin.trash');

        // How long the activity log - and with it the previous passwords -
        // is kept.
        Route::get('/log-retention', AdminLogRetention::class)
            ->middleware('can:admin_activity')->name('admin.logretention');

        // Settings of the installation
        Route::middleware('can:admin_setting')->group(function () {
            // Own name and own logos - Livewire, so every change applies at
            // once instead of only after a save button.
            Route::get('/general', AdminGeneral::class)->name('admin.general.index');

            // Password rules for signing in - see AdminSecurity.
            Route::get('/security', AdminSecurity::class)->name('admin.security.index');

            // Warning times and the retention of the PDF exports.
            Route::get('/retention', AdminRetention::class)->name('admin.retention.index');

            // Who gets the daily expiry mail, and what it reports.
            Route::get('/notifications', AdminNotifications::class)->name('admin.notifications');

            // Backup of DokuVault itself: schedule, external target, retention.
            // Defaults for the agents of all customers.
            Route::get('/agents', AdminAgentSettings::class)->name('admin.agents.settings');

            // Admin role only, not admin_setting: whoever sets the target and
            // the archive password can take every customer's data with them.
            Route::middleware('can:isAdmin')->group(function () {
                Route::get('/backup', AdminBackupSettings::class)->name('admin.backup.settings');
                Route::get('/backup/download', BackupDownloadController::class)->name('admin.backup.download');
            });

            Route::get('/setting', [SettingController::class, 'index'])->name('admin.setting.index');
            Route::patch('/setting', [SettingController::class, 'update'])->name('admin.setting.update');

            // Mail sending. Whatever stands here applies instead of the .env -
            // see AppServiceProvider::applyMailSettings().
            Route::get('/mail', [MailSettingController::class, 'index'])->name('admin.mail.index');
            Route::patch('/mail', [MailSettingController::class, 'update'])->name('admin.mail.update');
            Route::post('/mail/test', [MailSettingController::class, 'test'])->name('admin.mail.test');
            Route::delete('/mail/password', [MailSettingController::class, 'deletePassword'])
                ->name('admin.mail.password');
        });

        // Activity log - with search and filters, hence Livewire.
        Route::get('/activity', AdminActivityLog::class)
            ->middleware('can:admin_activity')->name('admin.activity.index');

        // Create and revoke tokens. This address used to return raw JSON and
        // created another token on every call - a menu item pointing at it
        // would have generated tokens when clicked.
        Route::get('/apitoken', AdminApiToken::class)
            ->middleware('can:admin_apitoken')->name('admin.apitoken');

        // Statistik: requests to the API per hour - when the load comes,
        // whether the server keeps up. Own right, more pages follow.
        Route::get('/api-load', AdminApiLoad::class)
            ->middleware('can:admin_stats')->name('admin.api-load');
        Route::middleware('can:admin_stats')->prefix('stats')->group(function () {
            Route::get('/system', AdminSystem::class)->name('admin.stats.system');
            Route::get('/data-growth', AdminDataGrowth::class)->name('admin.stats.data-growth');
            Route::get('/agents', AdminAgentStats::class)->name('admin.stats.agents');
            Route::get('/usage', AdminUsage::class)->name('admin.stats.usage');
        });

        // Backups of all customers with their last runs (reported by agents).
        Route::get('/backups', AdminBackups::class)
            ->middleware('can:admin_backup')->name('admin.backups');

        Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');

        // Customers
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

            // A lost phone: without this way a user with the second factor
            // switched on and no recovery code would be locked out for good.
            //
            Route::delete('/user/{user}/two-factor', [UserController::class, 'resetTwoFactor'])
                ->name('admin.user.two-factor');

            // Send the invitation again: landed in spam, link expired, deleted
            // by accident - that is everyday life, not a fault.
            Route::post('/user/{user}/invite', [UserController::class, 'invite'])
                ->name('admin.user.invite');
        });

        // Roles
        Route::middleware('can:admin_role')->group(function () {
            Route::get('/role', [RoleController::class, 'index'])->name('admin.role.index');
            Route::post('/role', [RoleController::class, 'store'])->name('admin.role.store');
            Route::get('/role/create', [RoleController::class, 'create'])->name('admin.role.create');
            Route::get('/role/{role}/edit', [RoleController::class, 'edit'])->name('admin.role.edit');
            Route::patch('/role/{role}', [RoleController::class, 'update'])->name('admin.role.update');
        });

        // Operating systems. The list stands in the menu under
        // "Auswahlmenues", beside services and mail providers - hence the same
        // permission. The separate permission carries the EOL evaluation,
        // which has a menu item of its own.
        Route::middleware('can:admin_catalog')->group(function () {
            // Livewire instead of a controller: the only change is the search,
            // create and edit stay pages of their own.
            Route::get('/operatingsystem', AdminOperatingSystem::class)->name('admin.operatingsystem.index');
            Route::post('/operatingsystem/create', [OperatingSystemController::class, 'store'])->name('admin.operatingsystem.store');
            Route::get('/operatingsystem/create', [OperatingSystemController::class, 'create'])->name('admin.operatingsystem.create');
            Route::get('/operatingsystem/{operatingSystem}/edit', [OperatingSystemController::class, 'edit'])->name('admin.operatingsystem.edit');
            Route::patch('/operatingsystem/{operatingSystem}', [OperatingSystemController::class, 'update'])->name('admin.operatingsystem.update');
        });

        // Affected devices by customer: which customer has how many machines
        // on a system whose support is ending? Belongs with the operating
        // systems - that is where the support end stands.
        Route::get('/eol', [EolController::class, 'index'])
            ->middleware('can:admin_operatingsystem')->name('admin.eol.index');

        // The three catalogs share one permission: whoever may maintain
        // services also maintains mailbox providers and rack items - it is the
        // same kind of work on the same select lists.
        Route::middleware('can:admin_catalog')->group(function () {
            // Service catalog (name and colour of the chips in the device lists)
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

            // Rack catalog (passive items such as patch panels and blanking plates)
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

// Remote support: logs, then redirects to the tool link. Without the customer
// in the address - the lists would otherwise load it per device; the
// controller checks the customer itself.
Route::middleware('auth')->get('/remote/{type}/{id}', RemoteConnectController::class)
    ->whereIn('type', ['server', 'vm', 'computer'])->whereNumber('id')->name('remote.connect');
Route::get('/customer/search', [CustomerController::class, 'search'])->name('customer.search');
Route::get('/{customer}', [CustomerController::class, 'dashboard'])->name('customer.dashboard');
Route::post('/{customer}/view-pdf', [CustomerController::class, 'viewPDF'])->name('customer.view-pdf');
// Collect the finished PDF. Only the person who ordered it may load it - it
// holds every credential of the customer.
Route::get('/{customer}/pdf/{pdfExport}', [CustomerController::class, 'downloadPDF'])->name('customer.pdf-download');

Route::middleware(['auth', 'isCustomer'])->group(function () {
    Route::prefix('{customer}')->group(function () {
        Route::scopeBindings()->group(function () {

            // Site
            Route::post('filter', [SiteController::class, 'filter'])->name('filter.site');

            // IP plan per VLAN
            Route::get('ip-plan', [IpPlanController::class, 'index'])->name('ipplan.index');

            // Logical network map per site
            Route::get('network-map', [NetworkMapController::class, 'index'])->name('networkmap.index');

            // On- and offboarding checklists
            Route::get('checklists', [ChecklistController::class, 'index'])->name('checklist.index');
            Route::get('checklists/settings', [ChecklistController::class, 'settings'])->name('checklist.settings');
            Route::get('checklists/onboarding/create', [ChecklistController::class, 'createOnboarding'])->name('checklist.onboarding.create');
            Route::get('checklists/offboarding/create', [ChecklistController::class, 'createOffboarding'])->name('checklist.offboarding.create');
            Route::post('checklists/offboarding', [ChecklistController::class, 'storeOffboarding'])->name('checklist.offboarding.store');
            Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklist.show');
            Route::get('checklists/{checklist}/ad-script', [ChecklistController::class, 'adScript'])->name('checklist.ad-script');
            Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklist.destroy');

            // Trash
            Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
            Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');

            Route::get('agent', [AgentTokenController::class, 'index'])->name('agent.index');
            Route::post('agent', [AgentTokenController::class, 'store'])->name('agent.store');
            Route::delete('agent/{agentToken}', [AgentTokenController::class, 'destroy'])->name('agent.destroy');
            Route::post('agent/{agentToken}/renew', [AgentTokenController::class, 'renew'])->name('agent.renew');
            Route::post('agent/{agentToken}/lock', [AgentTokenController::class, 'lock'])->name('agent.lock');
            Route::post('agent/{agentToken}/unlock', [AgentTokenController::class, 'unlock'])->name('agent.unlock');
            Route::get('agent/service.exe', [AgentTokenController::class, 'serviceExe'])->name('agent.service');
            Route::put('agent/installation/{agentInstallation}', [AgentTokenController::class, 'updateInstallation'])->name('agent.installation.update');
            Route::post('agent/installation/{agentInstallation}/run', [AgentTokenController::class, 'runInstallation'])->name('agent.installation.run');
            Route::delete('agent/installation/{agentInstallation}', [AgentTokenController::class, 'destroyInstallation'])->name('agent.installation.destroy');
            Route::get('agent/service-proxmox.sh', [AgentTokenController::class, 'serviceProxmox'])->name('agent.service.proxmox');
            Route::get('agent/service-linux.sh', [AgentTokenController::class, 'serviceLinux'])->name('agent.service.linux');

            // Documentation wizard (guided initial survey)
            Route::get('wizard', [WizardController::class, 'index'])->name('wizard.index');
            Route::post('wizard/complete', [WizardController::class, 'complete'])->name('wizard.complete');

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
            Route::get('networkshare', [NetworkShareController::class, 'index'])->name('networkshare.index');
            Route::resource('addomain', ADDomainController::class)->only(['index']);
            Route::resource('aduser', ADUserController::class)->only(['index']);
            Route::resource('adgroup', ADGroupController::class)->only(['index']);
            Route::resource('loginwebsite', LoginWebsiteController::class)->only(['index']);
            Route::resource('logingeneral', LoginGeneralController::class)->only(['index']);
            // The list only: create, edit and delete run through the modal.
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
            Route::resource('scanner', ScannerController::class)->only(['index']);
            Route::resource('sipaccount', SipAccountController::class)->only(['index']);
            Route::resource('scantarget', ScanTargetController::class)->only(['index']);
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
            // The list as Livewire - search, filter and sorting. Upload and
            // download stay with the controller.
            Route::get('file', [FileController::class, 'index'])->name('file.index');
            Route::post('file', [FileController::class, 'store'])->name('file.store');
            Route::delete('file/{file}', [FileController::class, 'destroy'])->name('file.destroy');
            Route::get('/file/{file}', [FileController::class, 'download']);

        });
    });
});
