<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Role;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function adminNutzer(): User
{
    // Die Rolle muss es geben: Die isAdmin-Middleware liest $user->role->id.
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);

    return User::factory()->create(['role_id' => $rolle->id]);
}

test('ohne Einstellung bleibt es bei RustDesk', function () {
    // Die Loesung war fest verdrahtet, bevor es die Einstellung gab - eine
    // bestehende Installation darf sich durch das Update nicht aendern.
    $tool = Setting::fernwartung();

    expect($tool['key'])->toBe('rustdesk');
    expect(Setting::fernwartungsLink('123456789', 'geheim'))
        ->toBe('rustdesk://connection/new/123456789?password=geheim');
});

test('die Umstellung wirkt auf Link und Beschriftung', function () {
    Setting::setzen(Setting::REMOTE_TOOL, 'teamviewer');

    expect(Setting::fernwartung()['id_label'])->toBe('TeamViewer ID');
    expect(Setting::fernwartungsLink('123456789', 'geheim'))
        ->toBe('teamviewer10://control?device=123456789');

    // TeamViewer kennt keine Kennwortuebergabe - der Knopf verbindet trotzdem.
    expect(Setting::fernwartungsLink('123456789', null))
        ->toBe('teamviewer10://control?device=123456789');
});

test('RustDesk verlangt beides, sonst gibt es keinen Knopf', function () {
    // Ein rustdesk-Link ohne Passwort landet in der Kennwortabfrage - dann ist
    // kein Knopf ehrlicher als ein Knopf, der nicht durchkommt.
    expect(Setting::fernwartungsLink('123456789', null))->toBeNull();
    expect(Setting::fernwartungsLink(null, 'geheim'))->toBeNull();
});

test('die Geraeteliste zeigt den Knopf der eingestellten Loesung', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['server_viewAny']));

    Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Debian 13'])->id,
        'name' => 'SRV-Fern', 'remoteID' => '987654321', 'remotePassword' => 'geheim',
    ]);

    $this->get(route('server.index', $customer))->assertSee('rustdesk://connection/new/987654321', false);

    Setting::setzen(Setting::REMOTE_TOOL, 'anydesk');

    $antwort = $this->get(route('server.index', $customer));
    $antwort->assertSee('anydesk:987654321', false);
    $antwort->assertDontSee('rustdesk://', false);
});

test('ein eigenes Muster mit javascript wird abgelehnt', function () {
    $this->actingAs(adminNutzer());

    // Aus dem Muster wird ein anklickbarer Link in jeder Geraeteliste. Ohne
    // diese Pruefung waere das ausfuehrbarer Code.
    foreach (['javascript:alert(1)', 'data:text/html,<script>', 'vbscript:msgbox'] as $boese) {
        $this->patch(route('admin.setting.update'), [
            'remote_tool' => 'custom',
            'remote_pattern' => $boese,
        ])->assertSessionHasErrors('remote_pattern');
    }

    expect(Setting::wert(Setting::REMOTE_PATTERN))->toBeNull();
    expect(Setting::wert(Setting::REMOTE_TOOL))->toBeNull();
});

test('ein eigenes Muster ohne Kennung wird abgelehnt', function () {
    $this->actingAs(adminNutzer());

    $this->patch(route('admin.setting.update'), [
        'remote_tool' => 'custom',
        'remote_pattern' => 'meintool://verbinden',
    ])->assertSessionHasErrors('remote_pattern');
});

test('ein gueltiges eigenes Muster wird uebernommen', function () {
    $this->actingAs(adminNutzer());

    $this->patch(route('admin.setting.update'), [
        'remote_tool' => 'custom',
        'remote_pattern' => 'meintool://connect?id={id}&pw={password}',
    ])->assertRedirect(route('admin.setting.index'));

    expect(Setting::fernwartungsLink('42', 'geheim'))
        ->toBe('meintool://connect?id=42&pw=geheim');
});

test('nur Admins kommen an die Einstellungen', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('admin.setting.index'))->assertForbidden();
    $this->patch(route('admin.setting.update'), ['remote_tool' => 'anydesk'])->assertForbidden();

    expect(Setting::wert(Setting::REMOTE_TOOL))->toBeNull();
});

test('die Einstellung kostet keine Abfrage je Geraet', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['server_viewAny']));

    Server::factory()->count(10)->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => $os->id, 'remoteID' => '1', 'remotePassword' => '2',
    ]);

    $abfragen = 0;
    DB::listen(function () use (&$abfragen) {
        $abfragen++;
    });

    $this->get(route('server.index', $customer));

    // Cache::rememberForever behandelt null als "nicht im Cache" - je Zeile eine
    // Abfrage waere die Folge gewesen. Gemessen hatte die Liste 114 statt 8.
    expect($abfragen)->toBeLessThan(20, "Liste braucht {$abfragen} Abfragen");
});

test('die Einstellungen stehen im Admin-Menue', function () {
    $this->actingAs(adminNutzer());

    // Als Aufklappmenue angelegt, weil weitere Einstellungen folgen werden -
    // eine Kachel im Dashboard-Raster ohne Zahl war dafuer der falsche Ort.
    $antwort = $this->get(route('admin.dashboard'));

    $antwort->assertSee('Einstellungen');
    $antwort->assertSee(route('admin.setting.index'), false);

    // Und der Weg dahinter funktioniert auch.
    $this->get(route('admin.setting.index'))
        ->assertOk()
        ->assertSee('Fernwartung')
        ->assertSee('RustDesk')
        ->assertSee('TeamViewer')
        ->assertSee('AnyDesk');
});
