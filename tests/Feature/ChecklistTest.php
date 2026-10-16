<?php

use App\Livewire\ChecklistProgress;
use App\Livewire\ChecklistSettings;
use App\Livewire\ObjektFormular;
use App\Livewire\OnboardingForm;
use App\Models\ADDomainHost;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Checklist;
use App\Models\ChecklistOption;
use App\Models\ChecklistSetting;
use App\Models\ChecklistTemplate;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\NAS;
use App\Models\NetworkShare;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Support\ChecklistBuilder;
use Livewire\Livewire;

function checklistCustomer(): Customer
{
    $customer = Customer::factory()->create();
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'kunde.de']);

    return $customer;
}

function checklistOption(Customer $customer, array $values): ChecklistOption
{
    return ChecklistOption::create(array_merge(['customer_id' => $customer->id, 'kind' => 'app', 'name' => 'Nextcloud'], $values));
}

/** A documented network drive, offered in the onboarding form, with groups for read and write. */
function onboardingShare(Customer $customer, array $values = []): NetworkShare
{
    $name = $values['name'] ?? 'Gemeinsam';
    $read = ADGroup::factory()->create(['customer_id' => $customer->id, 'name' => "DL_{$name}_R"]);
    $write = ADGroup::factory()->create(['customer_id' => $customer->id, 'name' => "DL_{$name}_RW"]);

    return NetworkShare::factory()->create(array_merge([
        'customer_id' => $customer->id, 'name' => $name, 'drive_letter' => 'S',
        'ad_group_read_id' => $read->id, 'ad_group_write_id' => $write->id, 'in_onboarding_form' => true,
    ], $values));
}

function onboardingRequest(array $values = []): array
{
    return array_merge([
        'first_name' => 'Sophie',
        'last_name' => 'Krämer',
        'reference_date' => '2026-11-16',
        'drives' => [],
        'options' => [],
    ], $values);
}

/*
|--------------------------------------------------------------------------
| Aus dem Antrag wird die Liste
|--------------------------------------------------------------------------
*/

test('der Benutzername folgt vorname.nachname, Umlaute umschrieben', function () {
    expect(ChecklistBuilder::username('Sophie', 'Krämer'))->toBe('sophie.kraemer')
        ->and(ChecklistBuilder::username('Karl-Wilhelm', 'Behrens'))->toBe('karl-wilhelm.behrens')
        ->and(ChecklistBuilder::username('Mert', 'Yıldız'))->toBe('mert.yildiz');
});

test('jedes Laufwerk wird ein Punkt mit der passenden AD-Gruppe', function () {
    $customer = checklistCustomer();
    $drive = onboardingShare($customer, ['name' => 'Buchhaltung', 'drive_letter' => 'B', 'sensitive' => true]);
    $app = checklistOption($customer, ['name' => 'Nextcloud']);

    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest([
        'drives' => [$drive->id => 'rw'],
        'options' => [$app->id],
    ]), null);

    $items = $checklist->items;

    expect($checklist->username)->toBe('sophie.kraemer')
        ->and($checklist->email)->toBe('sophie.kraemer@kunde.de')
        ->and($items->pluck('title'))->toContain('AD-Benutzer sophie.kraemer anlegen', 'B: Buchhaltung, lesen und schreiben', 'Nextcloud: Zugang einrichten');

    $driveItem = $items->firstWhere('section', 'Laufwerke');
    expect($driveItem->reference)->toBe('DL_Buchhaltung_RW')
        ->and($driveItem->needs_approval)->toBeTrue();

    // "Erster Tag" bleibt am Ende, die erzeugten Punkte stehen davor.
    expect($items->last()->section)->toBe('Erster Tag');
});

test('Auswahl eines anderen Kunden oder abgeschaltete Auswahl erzeugt nichts', function () {
    $customer = checklistCustomer();
    $foreign = checklistOption(Customer::factory()->create(), ['name' => 'Fremd']);
    $inactive = checklistOption($customer, ['name' => 'Alt', 'active' => false]);

    $items = app(ChecklistBuilder::class)->onboardingItems($customer, onboardingRequest(['options' => [$foreign->id, $inactive->id]]));

    expect($items->pluck('title')->implode(' '))->not->toContain('Fremd')->not->toContain('Alt');
});

test('ein abgeschalteter Standard-Schritt fehlt nur bei diesem Kunden, eigene Schritte kommen dazu', function () {
    $customer = checklistCustomer();
    $other = checklistCustomer();
    $template = ChecklistTemplate::ofType('onboarding');
    $standard = $template->items()->whereNull('customer_id')->where('title', 'like', 'Kurze Einweisung%')->firstOrFail();

    $customer->hiddenChecklistItems()->attach($standard->id);
    $template->items()->create(['customer_id' => $customer->id, 'section' => 'Konten', 'title' => 'DATEV-Zugang beantragen', 'assignee' => 'customer', 'sort' => 120]);

    $titles = fn (Customer $c) => app(ChecklistBuilder::class)->onboardingItems($c, onboardingRequest())->pluck('title');

    expect($titles($customer))->toContain('DATEV-Zugang beantragen')->not->toContain($standard->title)
        ->and($titles($other))->toContain($standard->title)->not->toContain('DATEV-Zugang beantragen');
});

test('Offboarding übernimmt Gruppen und Postfach des AD-Benutzers aus der Doku', function () {
    $customer = checklistCustomer();
    $adUser = ADUser::factory()->create(['customer_id' => $customer->id, 'firstName' => 'Ronald', 'lastName' => 'Kaufmann', 'username' => 'ronald.kaufmann', 'email' => 'ronald.kaufmann@kunde.de']);
    $group = ADGroup::factory()->create(['customer_id' => $customer->id, 'name' => 'GG_Technik']);
    $adUser->groups()->attach($group->id);
    Mailbox::factory()->create(['customer_id' => $customer->id, 'mailAdress' => 'Ronald.Kaufmann@kunde.de']);

    $checklist = app(ChecklistBuilder::class)->startOffboarding($customer, $adUser, '2026-10-31', null);

    expect($checklist->type)->toBe('offboarding')
        ->and($checklist->items->pluck('title'))->toContain(
            'AD-Benutzer ronald.kaufmann deaktivieren',
            'Aus Gruppe GG_Technik entfernen',
            'Postfach Ronald.Kaufmann@kunde.de umwandeln oder Weiterleitung einrichten',
        );
});

/*
|--------------------------------------------------------------------------
| Kunde meldet, IT arbeitet ab
|--------------------------------------------------------------------------
*/

test('ein Kundenkonto meldet einen neuen Mitarbeiter über das Formular', function () {
    $customer = checklistCustomer();
    $drive = onboardingShare($customer);
    $user = userWithPermissions(['checklist_request']);
    $user->update(['customer_id' => $customer->id]);

    $this->actingAs($user)->get("/{$customer->slug}/checklists/onboarding/create")->assertOk()->assertSee('Gemeinsam');

    Livewire::actingAs($user)->test(OnboardingForm::class, ['customer' => $customer])
        ->set('first_name', 'Sophie')
        ->set('last_name', 'Krämer')
        ->set("drives.{$drive->id}", 'r')
        ->assertSee('sophie.kraemer')
        ->assertSee('S: Gemeinsam, nur lesen')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $checklist = Checklist::firstOrFail();
    expect($checklist->requested_by)->toBe($user->id)
        ->and($checklist->request['drives'])->toBe([$drive->id => 'r']);
});

test('ein Kundenkonto kommt nicht an die Checklisten eines anderen Kunden', function () {
    $own = checklistCustomer();
    $foreign = checklistCustomer();
    $user = userWithPermissions(['checklist_request']);
    $user->update(['customer_id' => $own->id]);

    $this->actingAs($user)->get("/{$foreign->slug}/checklists")->assertForbidden();

    Livewire::actingAs($user)->test(OnboardingForm::class, ['customer' => $foreign])->assertForbidden();
});

test('ohne Recht keine Checklisten', function () {
    $customer = checklistCustomer();

    $this->actingAs(userWithPermissions(['site_viewAny']))->get("/{$customer->slug}/checklists")->assertForbidden();
});

test('der Kunde darf melden, aber nicht abhaken und nicht einrichten', function () {
    $customer = checklistCustomer();
    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(), null);
    $user = userWithPermissions(['checklist_request']);
    $user->update(['customer_id' => $customer->id]);

    $this->actingAs($user)->get("/{$customer->slug}/checklists/{$checklist->id}")->assertOk();
    $this->actingAs($user)->get("/{$customer->slug}/checklists/settings")->assertForbidden();

    Livewire::actingAs($user)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->call('toggle', $checklist->items->first()->id)
        ->assertForbidden();
});

test('IT hakt ab, die Liste schließt sich, wenn alles erledigt ist', function () {
    $customer = checklistCustomer();
    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(), null);
    $user = userWithPermissions(['checklist_manage']);

    $component = Livewire::actingAs($user)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist]);
    foreach ($checklist->items as $item) {
        $component->call('toggle', $item->id);
    }

    expect($checklist->fresh()->completed_at)->not->toBeNull()
        ->and($checklist->items()->whereNull('done_by')->exists())->toBeFalse();

    // Ein Haken zurück öffnet sie wieder.
    $component->call('toggle', $checklist->items->first()->id);
    expect($checklist->fresh()->completed_at)->toBeNull();
});

test('ein Laufwerk mit Freigabe lässt sich erst nach der Freigabe abhaken', function () {
    $customer = checklistCustomer();
    $drive = onboardingShare($customer, ['name' => 'GF', 'drive_letter' => 'G', 'sensitive' => true]);
    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(['drives' => [$drive->id => 'r']]), null);
    $item = $checklist->items()->where('needs_approval', true)->firstOrFail();

    $it = userWithPermissions(['checklist_manage']);
    Livewire::actingAs($it)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->call('toggle', $item->id);
    expect($item->fresh()->done_at)->toBeNull();

    $boss = userWithPermissions(['checklist_approve']);
    $boss->update(['customer_id' => $customer->id]);
    Livewire::actingAs($boss)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->call('approve', $item->id);
    expect($item->fresh()->approved_by)->toBe($boss->id);

    Livewire::actingAs($it)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->call('toggle', $item->id);
    expect($item->fresh()->done_at)->not->toBeNull();
});

test('IT startet ein Offboarding für einen AD-Benutzer des Kunden', function () {
    $customer = checklistCustomer();
    $adUser = ADUser::factory()->create(['customer_id' => $customer->id, 'username' => 'ronald.kaufmann', 'enabled' => true]);
    $foreignUser = ADUser::factory()->create(['customer_id' => checklistCustomer()->id]);
    $user = userWithPermissions(['checklist_manage']);

    $this->actingAs($user)->get("/{$customer->slug}/checklists/offboarding/create")->assertOk()->assertSee('ronald.kaufmann');

    $this->actingAs($user)->post("/{$customer->slug}/checklists/offboarding", ['ad_user_id' => $foreignUser->id, 'reference_date' => '2026-10-31'])
        ->assertNotFound();

    $this->actingAs($user)->post("/{$customer->slug}/checklists/offboarding", ['ad_user_id' => $adUser->id, 'reference_date' => '2026-10-31'])
        ->assertRedirect();

    expect(Checklist::where('type', 'offboarding')->where('ad_user_id', $adUser->id)->exists())->toBeTrue();
});

test('in den Einstellungen legt IT Auswahl an und schaltet Standard-Schritte ab', function () {
    $customer = checklistCustomer();
    $user = userWithPermissions(['checklist_manage']);
    $standard = ChecklistTemplate::ofType('onboarding')->items()->whereNull('customer_id')->firstOrFail();

    $this->actingAs($user)->get("/{$customer->slug}/checklists/settings")->assertOk();

    Livewire::actingAs($user)->test(ChecklistSettings::class, ['customer' => $customer])
        ->set('kind', 'app')
        ->set('name', 'Nextcloud')
        ->set('assignee', 'customer')
        ->call('saveOption')
        ->assertHasNoErrors()
        ->call('toggleStandard', $standard->id)
        ->set('steps.onboarding.section', 'Konten')
        ->set('steps.onboarding.title', 'DATEV-Zugang beantragen')
        ->call('addStep', 'onboarding')
        ->assertHasNoErrors();

    expect($customer->checklistOptions()->first()->assignee)->toBe('customer')
        ->and($customer->hiddenChecklistItems()->pluck('checklist_template_items.id')->all())->toBe([$standard->id])
        ->and(ChecklistTemplate::ofType('onboarding')->items()->where('customer_id', $customer->id)->value('title'))->toBe('DATEV-Zugang beantragen');
});

/*
|--------------------------------------------------------------------------
| Netzlaufwerke aus der Doku
|--------------------------------------------------------------------------
*/

test('nur angebotene Netzlaufwerke stehen im Formular, das Recht entscheidet die Gruppe', function () {
    $customer = checklistCustomer();
    $offered = onboardingShare($customer, ['name' => 'Projekte', 'drive_letter' => 'P']);
    $hidden = onboardingShare($customer, ['name' => 'Scans', 'drive_letter' => 'X', 'in_onboarding_form' => false]);

    $items = app(ChecklistBuilder::class)->onboardingItems($customer, onboardingRequest([
        'drives' => [$offered->id => 'r', $hidden->id => 'rw'],
    ]));

    $drives = $items->where('section', 'Laufwerke');
    expect($drives)->toHaveCount(1)
        ->and($drives->first()['title'])->toBe('P: Projekte, nur lesen')
        ->and($drives->first()['reference'])->toBe('DL_Projekte_R');
});

test('in den Checklisten-Einstellungen wird ein dokumentiertes Laufwerk nur an- und abgeschaltet', function () {
    $customer = checklistCustomer();
    $share = onboardingShare($customer, ['in_onboarding_form' => false]);
    $user = userWithPermissions(['checklist_manage']);

    Livewire::actingAs($user)->test(ChecklistSettings::class, ['customer' => $customer])
        ->assertSee($share->path)
        ->call('toggleShare', $share->id);

    expect($share->fresh()->in_onboarding_form)->toBeTrue();

    $foreign = onboardingShare(checklistCustomer());
    Livewire::actingAs($user)->test(ChecklistSettings::class, ['customer' => $customer])
        ->call('toggleShare', $foreign->id)
        ->assertNotFound();
});

test('Netzlaufwerke haben eine eigene Liste mit Pfad und Gruppen', function () {
    $customer = checklistCustomer();
    onboardingShare($customer, ['name' => 'Buchhaltung', 'drive_letter' => 'B', 'path' => '\\\\SRV-FS01\\Buchhaltung']);

    $this->actingAs(userWithPermissions(['networkshare_viewAny', 'adgroup_viewAny']))
        ->get("/{$customer->slug}/networkshare")
        ->assertOk()
        ->assertSeeInOrder(['B:', 'Buchhaltung', 'DL_Buchhaltung_R', 'DL_Buchhaltung_RW']);
});

test('ohne Netzlaufwerk-Recht keine Liste', function () {
    $customer = checklistCustomer();

    $this->actingAs(userWithPermissions(['nas_viewAny']))->get("/{$customer->slug}/networkshare")->assertForbidden();
});

test('der Laufwerksbuchstabe wird einheitlich gespeichert', function () {
    $share = NetworkShare::factory()->create(['customer_id' => checklistCustomer()->id, 'drive_letter' => 'p:']);

    expect($share->drive_letter)->toBe('P');
});

test('ein Netzlaufwerk liegt auf einem Server, einer VM oder einem NAS des Kunden', function () {
    $customer = checklistCustomer();
    $nas = NAS::factory()->create(['customer_id' => $customer->id, 'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id, 'name' => 'NAS01']);
    $other = checklistCustomer();
    $foreignNas = NAS::factory()->create(['customer_id' => $other->id, 'site_id' => Site::factory()->create(['customer_id' => $other->id])->id]);
    $this->actingAs(userWithPermissions(['networkshare_viewAny', 'networkshare_create', 'nas_viewAny']));

    imModal('networkshare', $customer, ['name' => 'Scans', 'path' => '\\\\NAS01\\scans', 'host' => "nas:{$foreignNas->id}"])
        ->assertHasErrors('form.host');

    imModal('networkshare', $customer, ['name' => 'Scans', 'path' => '\\\\NAS01\\scans', 'host' => "nas:{$nas->id}"])
        ->assertHasNoErrors();

    $share = NetworkShare::where('customer_id', $customer->id)->firstOrFail();
    expect($share->host->is($nas))->toBeTrue();

    $this->get("/{$customer->slug}/networkshare")->assertOk()->assertSee('NAS01');
});

test('die Domänencontroller-Auswahl bietet weiterhin nur Server und VMs', function () {
    $customer = checklistCustomer();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    NAS::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'NAS01']);
    Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-DC01', 'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id]);

    expect(ADDomainHost::options($customer->id))->toHaveCount(1)
        ->and(implode(' ', ADDomainHost::options($customer->id, ['server', 'vm', 'nas'])))->toContain('NAS01 (NAS)');
});

test('die Netzlaufwerke stehen nach Buchstabe sortiert, ohne Buchstabe am Ende', function () {
    $customer = checklistCustomer();
    foreach ([['Vorlagen', 'V'], ['Ohne', null], ['Buchhaltung', 'B'], ['Scans', 'X']] as [$name, $letter]) {
        NetworkShare::factory()->create(['customer_id' => $customer->id, 'name' => $name, 'drive_letter' => $letter, 'path' => "\\\\fs\\{$name}"]);
    }

    $this->actingAs(userWithPermissions(['networkshare_viewAny']))
        ->get("/{$customer->slug}/networkshare")
        ->assertOk()
        ->assertSeeInOrder(['Buchhaltung', 'Vorlagen', 'Scans', 'Ohne']);
});

test('die Wahl von Server oder NAS füllt den Pfad vor, ohne Eingetipptes zu überschreiben', function () {
    $customer = checklistCustomer();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $server = Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-FS01',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id]);
    $nas = NAS::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'NAS01']);
    $this->actingAs(userWithPermissions(['networkshare_viewAny', 'networkshare_create']));

    $form = Livewire::test(ObjektFormular::class, ['typ' => 'networkshare', 'customer' => $customer])->call('neu')
        ->set('form.host', "server:{$server->id}")
        ->assertSet('form.path', '\\\\SRV-FS01\\')
        ->set('form.path', '\\\\SRV-FS01\\Buchhaltung')
        ->set('form.host', "nas:{$nas->id}")
        ->assertSet('form.path', '\\\\NAS01\\Buchhaltung');

    $form->set('form.path', '\\\\fremd\\ablage')
        ->set('form.host', "server:{$server->id}")
        ->assertSet('form.path', '\\\\fremd\\ablage');
});

test('ein abgeschalteter Schalter kommt beim Bearbeiten nicht angehakt an', function () {
    $customer = checklistCustomer();
    $share = NetworkShare::factory()->create(['customer_id' => $customer->id, 'sensitive' => false]);
    $this->actingAs(userWithPermissions(['networkshare_viewAny', 'networkshare_create', 'networkshare_update']));

    Livewire::test(ObjektFormular::class, ['typ' => 'networkshare', 'customer' => $customer])
        ->call('neu')
        ->assertSet('form.sensitive', false)
        ->call('bearbeiten', 'networkshare', $share->id)
        ->assertSet('form.sensitive', false)
        ->set('form.sensitive', true)
        ->call('speichern')
        ->assertHasNoErrors();

    expect($share->fresh()->sensitive)->toBeTrue();
});

test('ein Programm verweist auf eine dokumentierte AD-Gruppe des Kunden', function () {
    $customer = checklistCustomer();
    $group = ADGroup::factory()->create(['customer_id' => $customer->id, 'name' => 'VPN-Benutzer']);
    $foreign = ADGroup::factory()->create(['customer_id' => checklistCustomer()->id]);
    $user = userWithPermissions(['checklist_manage']);

    Livewire::actingAs($user)->test(ChecklistSettings::class, ['customer' => $customer])
        ->assertSee('VPN-Benutzer')
        ->set('kind', 'app')
        ->set('name', 'VPN')
        ->set('ad_group_id', (string) $foreign->id)
        ->call('saveOption')
        ->assertHasErrors('ad_group_id')
        ->set('ad_group_id', (string) $group->id)
        ->call('saveOption')
        ->assertHasNoErrors();

    $option = $customer->checklistOptions()->firstOrFail();
    expect($option->ad_group_id)->toBe($group->id);

    $item = app(ChecklistBuilder::class)->onboardingItems($customer, onboardingRequest(['options' => [$option->id]]))
        ->firstWhere('title', 'VPN: Zugang einrichten');
    expect($item['reference'])->toBe('VPN-Benutzer');
});

test('Benutzername und E-Mail sind optional, ohne Angabe gilt der Vorschlag', function () {
    $customer = checklistCustomer();

    $own = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(['username' => 'skraemer', 'email' => 'sophie@kunde.de']), null);
    $suggested = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(['username' => '', 'email' => '']), null);

    expect([$own->username, $own->email])->toBe(['skraemer', 'sophie@kunde.de'])
        ->and($own->items->pluck('title'))->toContain('AD-Benutzer skraemer anlegen')
        ->and([$suggested->username, $suggested->email])->toBe(['sophie.kraemer', 'sophie.kraemer@kunde.de']);
});

test('die IT ändert Benutzername und E-Mail, die Punkte ziehen mit', function () {
    $customer = checklistCustomer();
    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(), null);

    Livewire::actingAs(userWithPermissions(['checklist_manage']))
        ->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->assertSet('username', 'sophie.kraemer')
        ->set('username', 'skraemer')
        ->set('email', 'kein-gueltiger-wert')
        ->call('saveAccount')
        ->assertHasErrors('email')
        ->set('email', 's.kraemer@kunde.de')
        ->call('saveAccount')
        ->assertHasNoErrors();

    $checklist->refresh();
    expect([$checklist->username, $checklist->email])->toBe(['skraemer', 's.kraemer@kunde.de'])
        ->and($checklist->items->pluck('title'))->toContain('AD-Benutzer skraemer anlegen')
        ->not->toContain('AD-Benutzer sophie.kraemer anlegen');
});

test('der Kunde kann Benutzername und E-Mail auf der Liste nicht ändern', function () {
    $customer = checklistCustomer();
    $checklist = app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest(), null);
    $user = userWithPermissions(['checklist_request']);
    $user->update(['customer_id' => $customer->id]);

    Livewire::actingAs($user)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->set('username', 'anders')
        ->call('saveAccount')
        ->assertForbidden();
});

test('die Schritte fragen keine Fälligkeit ab und zeigen keine', function () {
    $customer = checklistCustomer();

    Livewire::actingAs(userWithPermissions(['checklist_manage']))
        ->test(ChecklistSettings::class, ['customer' => $customer])
        ->assertDontSeeHtml('id="step-onboarding-due"')
        ->assertDontSee('T−5')
        ->assertDontSee('T+30')
        ->assertDontSeeHtml('id="step-offboarding-due"')
        ->set('steps.onboarding.section', 'Konten')
        ->set('steps.onboarding.title', 'Drucker zuweisen')
        ->call('addStep', 'onboarding')
        ->assertHasNoErrors();

    expect(ChecklistTemplate::ofType('onboarding')->items()->where('customer_id', $customer->id)->value('due_offset'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| PowerShell-Skript fürs AD
|--------------------------------------------------------------------------
*/

function onboardingWithDrives(Customer $customer): Checklist
{
    $open = onboardingShare($customer, ['name' => 'Projekte', 'drive_letter' => 'P']);
    $secret = onboardingShare($customer, ['name' => 'GF', 'drive_letter' => 'G', 'sensitive' => true]);

    return app(ChecklistBuilder::class)->startOnboarding($customer, onboardingRequest([
        'drives' => [$open->id => 'rw', $secret->id => 'r'],
    ]), null);
}

test('das Skript enthält Benutzer und freigegebene Gruppen, aber keine wartenden', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $user = userWithPermissions(['checklist_manage']);

    $response = $this->actingAs($user)->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="onboarding-sophie.kraemer.ps1"');

    $script = $response->getContent();
    expect($script)->toStartWith("\u{FEFF}")
        ->toContain('New-ADUser')
        ->toContain('-Verb RunAs')
        ->toContain('Wait-ForClose')
        ->toContain('proxyAddresses')
        ->toContain('"email": "sophie.kraemer@kunde.de"')
        ->toContain('"username": "sophie.kraemer"')
        ->toContain('DL_Projekte_RW')
        ->not->toContain('DL_GF_R')
        ->toContain("\r\n");

    // Only the hash is stored; the plain key sits in the file alone.
    preg_match('/\$Token = "([A-Za-z0-9]+)"/', $script, $match);
    $checklist->refresh();
    expect($checklist->script_token_hash)->toBe(hash('sha256', $match[1]))
        ->and($checklist->script_requested_by)->toBe($user->id);
});

test('der Kunde kann das Skript nicht herunterladen', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $user = userWithPermissions(['checklist_request', 'checklist_approve']);
    $user->update(['customer_id' => $customer->id]);

    $this->actingAs($user)->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")->assertForbidden();
});

test('die Rückmeldung dokumentiert den AD-Benutzer mit Kennwort und hakt nur erlaubte Punkte ab', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $other = onboardingWithDrives(checklistCustomer());
    $token = $checklist->issueScriptToken(userWithPermissions(['checklist_manage']));

    $create = $checklist->items()->where('action', 'create_ad_user')->firstOrFail();
    $group = $checklist->items()->where('reference', 'DL_Projekte_RW')->firstOrFail();
    $waiting = $checklist->items()->where('needs_approval', true)->firstOrFail();
    $manual = $checklist->items()->whereNull('action')->firstOrFail();

    $this->withToken($token)->postJson('/api/checklist-report', [
        'identifier' => '6f1c2d3e-0000-4000-8000-000000000001',
        'username' => 'sophie.kraemer',
        'firstName' => 'Sophie',
        'lastName' => 'Krämer',
        'email' => 'sophie.kraemer@kunde.de',
        'password' => 'Start-Kennwort-123!',
        'groups' => ['DL_Projekte_RW', 'DL_GF_R'],
        'items' => [$create->id, $group->id, $waiting->id, $manual->id, $other->items->first()->id],
    ])->assertOk()->assertJson(['done' => 2]);

    $adUser = ADUser::where('customer_id', $customer->id)->where('username', 'sophie.kraemer')->firstOrFail();
    expect($adUser->password)->toBe('Start-Kennwort-123!')
        ->and($adUser->getRawOriginal('password'))->not->toBe('Start-Kennwort-123!')
        ->and($adUser->agent_identifier)->toBe('6f1c2d3e-0000-4000-8000-000000000001')
        ->and($adUser->enabled)->toBeTrue()
        ->and($adUser->groups->pluck('name')->all())->toContain('DL_Projekte_RW', 'DL_GF_R');

    expect($create->fresh()->done_at)->not->toBeNull()
        ->and($group->fresh()->done_at)->not->toBeNull()
        ->and($waiting->fresh()->done_at)->toBeNull()
        ->and($manual->fresh()->done_at)->toBeNull()
        ->and($other->items->first()->fresh()->done_at)->toBeNull()
        ->and($checklist->fresh()->ad_user_id)->toBe($adUser->id);
});

test('ohne gültigen Schlüssel nimmt die Schnittstelle nichts an', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $token = $checklist->issueScriptToken(userWithPermissions(['checklist_manage']));
    $body = ['username' => 'sophie.kraemer', 'items' => [$checklist->items->first()->id]];

    $this->postJson('/api/checklist-report', $body)->assertUnauthorized();
    $this->withToken('falsch')->postJson('/api/checklist-report', $body)->assertUnauthorized();

    $this->travel(Checklist::SCRIPT_TOKEN_HOURS + 1)->hours();
    $this->withToken($token)->postJson('/api/checklist-report', $body)->assertUnauthorized();

    expect(ADUser::count())->toBe(0);
});

test('ein neuer Download macht den alten Schlüssel ungültig', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $user = userWithPermissions(['checklist_manage']);

    $old = $checklist->issueScriptToken($user);
    $checklist->issueScriptToken($user);

    $this->withToken($old)->postJson('/api/checklist-report', ['username' => 'sophie.kraemer'])->assertUnauthorized();
});

test('beim Offboarding meldet das Skript den deaktivierten Benutzer und die entfernten Gruppen', function () {
    $customer = checklistCustomer();
    $adUser = ADUser::factory()->create(['customer_id' => $customer->id, 'username' => 'ronald.kaufmann', 'enabled' => true]);
    $group = ADGroup::factory()->create(['customer_id' => $customer->id, 'name' => 'GG_Technik']);
    $adUser->groups()->attach($group->id);
    $checklist = app(ChecklistBuilder::class)->startOffboarding($customer, $adUser, '2026-10-31', null);
    $user = userWithPermissions(['checklist_manage']);

    $script = $this->actingAs($user)->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")->assertOk()->getContent();
    expect($script)->toContain('Disable-ADAccount')->toContain('GG_Technik');
    preg_match('/\$Token = "([A-Za-z0-9]+)"/', $script, $match);

    $items = $checklist->items()->whereNotNull('action')->pluck('id')->all();
    $this->withToken($match[1])->postJson('/api/checklist-report', [
        'username' => 'ronald.kaufmann', 'enabled' => false, 'password' => 'Zufall-xyz-987!',
        'groups' => ['GG_Technik'], 'items' => $items,
    ])->assertOk();

    $adUser->refresh();
    expect($adUser->enabled)->toBeFalse()
        ->and($adUser->password)->toBe('Zufall-xyz-987!')
        ->and($adUser->groups)->toHaveCount(0)
        ->and($checklist->items()->whereNotNull('action')->whereNull('done_at')->count())->toBe(0);
});

test('die Kennwortänderung bei der ersten Anmeldung ist aus, bis die IT sie anhakt', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $user = userWithPermissions(['checklist_manage']);

    expect(ChecklistTemplate::ofType('onboarding')->items()->pluck('title'))->toContain('Start-Kennwort setzen');

    $script = $this->actingAs($user)->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")->getContent();
    expect($script)->toContain('"changePasswordAtLogon": false')
        ->toContain('ChangePasswordAtLogon = [bool]$u.changePasswordAtLogon');

    Livewire::actingAs($user)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->assertSee('Kennwort bei der ersten Anmeldung ändern lassen')
        ->set('forcePasswordChange', true);

    expect($checklist->fresh()->force_password_change)->toBeTrue();

    $script = $this->actingAs($user)->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")->getContent();
    expect($script)->toContain('"changePasswordAtLogon": true');
});

test('der Kunde kann die Kennwortoption nicht umschalten', function () {
    $customer = checklistCustomer();
    $checklist = onboardingWithDrives($customer);
    $user = userWithPermissions(['checklist_request']);
    $user->update(['customer_id' => $customer->id]);

    Livewire::actingAs($user)->test(ChecklistProgress::class, ['customer' => $customer, 'checklist' => $checklist])
        ->set('forcePasswordChange', true)
        ->assertForbidden();

    expect($checklist->fresh()->force_password_change)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Start-Kennwort je Kunde
|--------------------------------------------------------------------------
*/

test('die Kennwort-Regel des Kunden steht im Skript, ohne Kennwort', function () {
    $customer = checklistCustomer();
    Livewire::actingAs(userWithPermissions(['checklist_manage']))
        ->test(ChecklistSettings::class, ['customer' => $customer])
        ->set('passwordLength', 8)
        ->set('passwordUppercase', 2)
        ->set('passwordSpecialCount', 2)
        ->set('passwordDigits', 3)
        ->set('passwordSpecials', '!-!')
        ->call('savePasswordRule')
        ->assertHasNoErrors();

    $setting = ChecklistSetting::for($customer);
    expect($setting->password_specials)->toBe('!-');

    $example = $setting->example();
    expect(strlen($example))->toBe(8)
        ->and(preg_match_all('/[A-Z]/', $example))->toBe(2)
        ->and(preg_match_all('/[!\-]/', $example))->toBe(2)
        ->and(preg_match_all('/[0-9]/', $example))->toBe(3)
        ->and(preg_match_all('/[a-z]/', $example))->toBe(1)
        ->and($example)->not->toMatch('/[lIO01]/');

    $checklist = onboardingWithDrives($customer);
    $script = $this->actingAs(userWithPermissions(['checklist_manage']))
        ->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")->getContent();
    expect($script)->toContain('"mode": "generated"')->toContain('"uppercase": 2')->toContain('"specialCount": 2')->toContain('"digits": 3')->toContain('"specials": "!-"')
        ->toContain('New-StartPassword $Data.password');
});

test('ein festes Start-Kennwort wird verschlüsselt gespeichert und erzwingt die Änderung', function () {
    $customer = checklistCustomer();
    Livewire::actingAs(userWithPermissions(['checklist_manage']))
        ->test(ChecklistSettings::class, ['customer' => $customer])
        ->set('passwordMode', 'fixed')
        ->call('savePasswordRule')
        ->assertHasErrors('fixedPassword')
        ->set('fixedPassword', 'Willkommen2026!')
        ->call('savePasswordRule')
        ->assertHasNoErrors();

    $setting = ChecklistSetting::for($customer);
    expect($setting->fixed_password)->toBe('Willkommen2026!')
        ->and($setting->getRawOriginal('fixed_password'))->not->toContain('Willkommen');

    $checklist = onboardingWithDrives($customer);
    expect($checklist->force_password_change)->toBeTrue();

    $script = $this->actingAs(userWithPermissions(['checklist_manage']))
        ->get("/{$customer->slug}/checklists/{$checklist->id}/ad-script")->getContent();
    expect($script)->toContain('"fixed": "Willkommen2026!"')->toContain('"changePasswordAtLogon": true');
});

test('neue Checklisten übernehmen die Vorgabe zur Kennwortänderung', function () {
    $customer = checklistCustomer();
    ChecklistSetting::create(['customer_id' => $customer->id, 'force_change_default' => true]);

    expect(onboardingWithDrives($customer)->force_password_change)->toBeTrue();
});

test('die Regel muss Platz für Großbuchstaben, Sonderzeichen und Ziffer lassen', function () {
    Livewire::actingAs(userWithPermissions(['checklist_manage']))
        ->test(ChecklistSettings::class, ['customer' => checklistCustomer()])
        ->set('passwordLength', 6)
        ->set('passwordUppercase', 4)
        ->set('passwordSpecialCount', 2)
        ->set('passwordDigits', 1)
        ->set('passwordSpecials', 'ab')
        ->call('savePasswordRule')
        ->assertHasErrors('passwordSpecials')
        ->set('passwordSpecials', '!')
        ->call('savePasswordRule')
        ->assertHasErrors('passwordLength')
        ->set('passwordLength', 7)
        ->call('savePasswordRule')
        ->assertHasNoErrors();
});
