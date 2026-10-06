<?php

use App\Http\Middleware\RecordApiRequest;
use App\Livewire\AdminAuslastung;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/*
 * API requests are counted per hour and endpoint (api_request_stats) and
 * shown under Admin -> Statistik -> API-Auslastung.
 */

function zaehle(string $pfad, int $status = 200): void
{
    $request = Request::create('/'.$pfad, 'POST');
    $request->setRouteResolver(fn () => tap(new Route('POST', $pfad, []), fn ($r) => $r->bind($request)));
    (new RecordApiRequest)->terminate($request, new Response('', $status));
}

test('requests are counted per hour and endpoint, errors separately', function () {
    zaehle('api/agent/checkin');
    zaehle('api/agent/checkin');
    zaehle('api/agent/proxmox', 500);
    zaehle('api/user');

    $zeilen = DB::table('api_request_stats')->get()->keyBy('pfad');

    expect($zeilen)->toHaveCount(3)
        ->and($zeilen['api/agent/checkin']->anzahl)->toBe(2)
        ->and($zeilen['api/agent/checkin']->art)->toBe('agent')
        ->and($zeilen['api/agent/proxmox']->fehler)->toBe(1)
        ->and($zeilen['api/user']->art)->toBe('api');
});

test('a real API request is counted, even when it is turned away', function () {
    $this->postJson('/api/agent/checkin')->assertUnauthorized();

    // terminate() runs after the response; the test kernel calls it too.
    expect(DB::table('api_request_stats')->where('pfad', 'api/agent/checkin')->value('anzahl'))->toBe(1);
});

test('the load page shows the figures and needs the settings right', function () {
    zaehle('api/agent/checkin');
    zaehle('api/agent/checkin');

    $this->actingAs(userWithPermissions(['admin_setting']));
    Livewire::test(AdminAuslastung::class)
        ->assertOk()
        ->assertViewHas('summe', fn ($s) => $s['anzahl'] === 2 && $s['agent'] === 2)
        ->assertSee('api/agent/checkin')
        ->set('zeitraum', '24h')
        ->assertViewHas('verlauf', fn ($v) => count($v) === 24);
});

test('without the settings right there is no load page', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));

    $this->get(route('admin.auslastung'))->assertForbidden();
});
