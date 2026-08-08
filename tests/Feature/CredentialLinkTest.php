<?php

use App\Livewire\DeviceCredentials;
use App\Models\CredentialLink;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\NAS;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function zugangsUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $vm = VM::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'VM-WEB01', 'operating_system_id' => $os->id,
    ]);
    $login = LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'Linux root',
        'username' => 'root', 'password' => 'geheim123',
    ]);

    return [$customer, $site, $vm, $login];
}

test('ein vorhandenes Login lässt sich an eine VM hängen', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->set('login_id', $login->id)
        ->set('note', 'SSH root')
        ->call('attach');

    $link = $vm->fresh()->credentialLinks()->first();
    expect($link->login_general_id)->toBe($login->id);
    expect($link->note)->toBe('SSH root');
    expect($link->customer_id)->toBe($customer->id);
});

test('dasselbe Login hängt an mehreren Systemen', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'server_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-01', 'operating_system_id' => $vm->operating_system_id,
    ]);

    foreach ([$vm, $server] as $geraet) {
        Livewire::test(DeviceCredentials::class, ['model' => $geraet, 'customer' => $customer])
            ->set('login_id', $login->id)->call('attach');
    }

    // Genau der Punkt der Übung: ein Passwort, eine Stelle, mehrere Systeme.
    expect(LoginGeneral::count())->toBe(1);
    expect($login->fresh()->verwendetBei())->toBe('SRV-01 (Server), VM-WEB01 (VM)');
});

test('ein Login eines fremden Kunden lässt sich nicht anhängen', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    $fremd = Customer::factory()->create();
    $fremdesLogin = LoginGeneral::create([
        'customer_id' => $fremd->id, 'name' => 'Fremd', 'username' => 'x', 'password' => 'y',
    ]);

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->set('login_id', $fremdesLogin->id)
        ->call('attach')
        ->assertHasErrors('login_id');

    expect(CredentialLink::count())->toBe(0);
});

test('ohne Recht am Gerät bleibt die Komponente verschlossen', function () {
    $this->actingAs(userWithPermissions(['logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->set('login_id', $login->id)
        ->call('attach')
        ->assertForbidden();
});

test('ohne Recht auf die Logins bleibt die Komponente verschlossen', function () {
    $this->actingAs(userWithPermissions(['vm_update']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->set('login_id', $login->id)
        ->call('attach')
        ->assertForbidden();
});

test('ein neues Login lässt sich direkt am Gerät anlegen', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny', 'logingeneral_create']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->set('neu', true)
        ->set('name', 'Konsole')
        ->set('username', 'admin')
        ->set('password', 'S3hrGeheim')
        ->call('create');

    $neu = LoginGeneral::where('name', 'Konsole')->first();
    expect($neu->username)->toBe('admin');
    expect($neu->password)->toBe('S3hrGeheim');
    expect($vm->fresh()->credentialLinks()->pluck('login_general_id'))->toContain($neu->id);

    // Verschlüsselt in der Datenbank, nicht im Klartext.
    expect(DB::table('login_generals')->where('id', $neu->id)->value('password'))
        ->not->toBe('S3hrGeheim');
});

test('ohne logingeneral_create lässt sich kein neues Login anlegen', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->set('neu', true)->set('name', 'Konsole')
        ->call('create')
        ->assertForbidden();

    expect(LoginGeneral::count())->toBe(1);
});

test('Lösen entfernt die Verknüpfung, nicht das Login', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $link = $vm->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $login->id,
    ]);

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->call('detach', $link->id);

    expect(CredentialLink::count())->toBe(0);
    expect(LoginGeneral::whereKey($login->id)->exists())->toBeTrue();
});

test('dasselbe Login zweimal anzuhängen legt keinen zweiten Eintrag an', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    $komponente = Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer]);
    $komponente->set('login_id', $login->id)->call('attach');
    $komponente->set('login_id', $login->id)->call('attach');

    expect(CredentialLink::count())->toBe(1);
});

test('ein Gerät im Papierkorb erscheint nicht mehr unter „Verwendet bei"', function () {
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $vm->credentialLinks()->create(['customer_id' => $customer->id, 'login_general_id' => $login->id]);

    expect($login->fresh()->verwendetBei())->toBe('VM-WEB01 (VM)');

    $vm->delete();
    expect($login->fresh()->verwendetBei())->toBe('');

    // Die Verknüpfung überlebt den Papierkorb - beim Wiederherstellen ist sie zurück.
    $vm->restore();
    expect($login->fresh()->verwendetBei())->toBe('VM-WEB01 (VM)');
});

test('ein Login im Papierkorb verschwindet aus den Zugangsdaten des Geräts', function () {
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $vm->credentialLinks()->create(['customer_id' => $customer->id, 'login_general_id' => $login->id]);

    expect($vm->zugangsdaten())->toHaveCount(1);

    $login->delete();
    expect($vm->fresh()->zugangsdaten())->toHaveCount(0);
});

test('die Login-Liste nennt die verknüpften Systeme', function () {
    $nutzer = userWithPermissions(['logingeneral_viewAny']);
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $vm->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $login->id, 'note' => 'SSH root',
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/logingeneral")
        ->assertSee('Verwendet bei')
        ->assertSee('VM-WEB01 (VM)');
});

test('das Login-Formular zeigt die Verwendung mit Notiz', function () {
    $nutzer = userWithPermissions(['logingeneral_update']);
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $vm->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $login->id, 'note' => 'SSH root',
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/logingeneral/{$login->id}/edit")
        ->assertSee('VM-WEB01 (VM)')
        ->assertSee('SSH root');
});

test('die VM-Seite zeigt den Zugangsdaten-Abschnitt', function () {
    $nutzer = userWithPermissions(['vm_update', 'logingeneral_viewAny']);
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    $this->actingAs($nutzer)->get("/{$customer->slug}/vm/{$vm->id}/edit")
        ->assertSee('Zugangsdaten');
});

test('die Spalte Verwendung erscheint nur, wenn eine Notiz gepflegt ist', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $link = $vm->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $login->id,
    ]);

    // Ohne Notiz waere die Spalte eine Wiederholung des Namens.
    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->assertDontSee('Verwendung</th>', false);

    $link->update(['note' => 'Serielle Konsole']);

    Livewire::test(DeviceCredentials::class, ['model' => $vm, 'customer' => $customer])
        ->assertSee('Verwendung</th>', false)
        ->assertSee('Serielle Konsole');
});

test('die eigenen Login-Typen für NAS und Recorder gibt es nicht mehr', function () {
    // Sie waren derselbe Mechanismus mit fest verdrahteter Geräte-ID.
    expect(Route::has('loginnas.index'))->toBeFalse();
    expect(Route::has('loginrecorder.index'))->toBeFalse();

    expect(config('custom.permissions'))->not->toContain('LoginNAS');
    expect(config('custom.permissions'))->not->toContain('LoginRecorder');
    expect(array_keys(config('custom.trashables')))->not->toContain('loginnas');

    expect(Schema::hasTable('login_nas'))->toBeFalse();
    expect(Schema::hasTable('login_recorders'))->toBeFalse();
});

test('die alten Adressen liefern 404', function () {
    $nutzer = userWithPermissions(['nas_viewAny']);
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    $this->actingAs($nutzer)->get("/{$customer->slug}/loginnas")->assertNotFound();
    $this->actingAs($nutzer)->get("/{$customer->slug}/loginrecorder")->assertNotFound();
});

test('ein NAS im Papierkorb nimmt seine Zugangsdaten nicht mit', function () {
    $nutzer = userWithPermissions(['nas_delete']);
    [$customer, $site, $vm, $login] = zugangsUmgebung();
    $nas = NAS::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'NAS-01',
        'username' => 'admin', 'password' => 'geheim',
    ]);
    $nas->credentialLinks()->create(['customer_id' => $customer->id, 'login_general_id' => $login->id]);

    $this->actingAs($nutzer)->delete("/{$customer->slug}/nas/{$nas->id}");

    // Frueher loeschte der Controller die Geraete-Logins mit - jetzt kann
    // dasselbe Login an weiteren Systemen haengen und bleibt bestehen.
    expect(LoginGeneral::whereKey($login->id)->exists())->toBeTrue();
    expect($nas->fresh()->trashed())->toBeTrue();

    $nas->restore();
    expect($nas->fresh()->zugangsdaten())->toHaveCount(1);
});

test('ein Umbau der Verknüpfung landet im Aktivitätsprotokoll', function () {
    $this->actingAs(userWithPermissions(['vm_update', 'logingeneral_viewAny']));
    [$customer, $site, $vm, $login] = zugangsUmgebung();

    $link = $vm->credentialLinks()->create(['customer_id' => $customer->id, 'login_general_id' => $login->id]);
    $link->delete();

    $ereignisse = Activity::where('subject_type', CredentialLink::class)->pluck('event');
    expect($ereignisse)->toContain('created');
    expect($ereignisse)->toContain('deleted');
});
